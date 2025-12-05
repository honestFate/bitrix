(function() {
    'use strict';

    const CONFIG = {
        color: '#FFE0B2',
        dotColor: '#FF9800',
        textColor: '#E65100',
        hourHeight: 60,
        lineHeight: 20,
        ajaxUrl: '/local/ajax/calendar_activities.php'
    };

    // ===================
    // Утилиты
    // ===================
    
    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function parseDate(dateStr) {
        const [d, m, y] = dateStr.split('.');
        return new Date(y, m - 1, d);
    }

    function loadActivities(dateFrom, dateTo) {
        const url = `${CONFIG.ajaxUrl}?date_from=${dateFrom}&date_to=${dateTo}`;
        return fetch(url).then(r => r.json()).catch(() => []);
    }

    function openActivity(activityId, ownerType, ownerId) {
        const urls = {
            lead: `/crm/lead/details/${ownerId}/`,
            deal: `/crm/deal/details/${ownerId}/`,
            contact: `/crm/contact/details/${ownerId}/`,
            company: `/crm/company/details/${ownerId}/`
        };
        const url = urls[ownerType] || urls.deal;
        
        if (BX.SidePanel && BX.SidePanel.Instance) {
            BX.SidePanel.Instance.open(url);
        } else {
            window.open(url, '_blank');
        }
    }

    // ===================
    // Определение вида календаря (улучшенное)
    // ===================

    function getCalendarView() {
        // Проверяем контейнеры в порядке приоритета
        const dayContainer = document.querySelector('.calendar-grid-day-container');
        const weekContainer = document.querySelector('.calendar-grid-week-container');
        const monthContainer = document.querySelector('.calendar-grid-month-container');
        
        // Проверяем видимость контейнеров
        if (dayContainer && dayContainer.offsetParent !== null) {
            return 'day';
        }
        if (weekContainer && weekContainer.offsetParent !== null) {
            return 'week';
        }
        if (monthContainer && monthContainer.offsetParent !== null) {
            return 'month';
        }
        
        // Fallback по наличию элементов
        if (dayContainer) return 'day';
        if (weekContainer) return 'week';
        if (monthContainer) return 'month';
        
        return null;
    }

    function getVisibleDateRange() {
        const view = getCalendarView();
        let dateFrom, dateTo;

        if (view === 'day') {
            const dayCell = document.querySelector('.calendar-grid-day-container [data-bx-calendar-timeline-day]');
            if (dayCell) {
                const dateStr = dayCell.getAttribute('data-bx-calendar-timeline-day');
                const date = new Date(dateStr);
                return { dateFrom: formatDate(date), dateTo: formatDate(date), view };
            }
        }

        if (view === 'week') {
            const weekCells = document.querySelectorAll('.calendar-grid-week-container [data-bx-calendar-timeline-day]');
            if (weekCells.length >= 7) {
                const firstDate = new Date(weekCells[0].getAttribute('data-bx-calendar-timeline-day'));
                const lastDate = new Date(weekCells[6].getAttribute('data-bx-calendar-timeline-day'));
                return { dateFrom: formatDate(firstDate), dateTo: formatDate(lastDate), view };
            }
        }

        if (view === 'month') {
            const todayCell = document.querySelector('.calendar-grid-month-container .calendar-grid-today');
            if (todayCell) {
                const attr = todayCell.getAttribute('data-bx-calendar-timeline-day');
                if (attr) {
                    const today = new Date(attr);
                    dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
                    dateTo = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    return { dateFrom: formatDate(dateFrom), dateTo: formatDate(dateTo), view };
                }
            }
            
            // Fallback - ищем любую ячейку с датой
            const anyCell = document.querySelector('.calendar-grid-month-container [data-bx-calendar-timeline-day]');
            if (anyCell) {
                const attr = anyCell.getAttribute('data-bx-calendar-timeline-day');
                const date = new Date(attr);
                dateFrom = new Date(date.getFullYear(), date.getMonth(), 1);
                dateTo = new Date(date.getFullYear(), date.getMonth() + 1, 0);
                return { dateFrom: formatDate(dateFrom), dateTo: formatDate(dateTo), view };
            }
        }

        // Последний fallback
        const now = new Date();
        return { 
            dateFrom: formatDate(new Date(now.getFullYear(), now.getMonth(), 1)),
            dateTo: formatDate(new Date(now.getFullYear(), now.getMonth() + 1, 0)),
            view: view || 'month'
        };
    }

    // ===================
    // Создание элементов
    // ===================

    // Вид "Месяц"
    function createMonthElement(activity, dayIndex, topOffset) {
        const el = document.createElement('div');
        el.className = 'calendar-event-line-wrap calendar-event-line-fill calendar-activity-item';
        el.dataset.activityId = activity.id;
        el.style.cssText = `
            top: ${topOffset}px;
            left: calc(${dayIndex * 14.2857}% + 2px);
            width: calc(14.2857% - 5px);
            cursor: pointer;
        `;

        el.innerHTML = `
            <div class="calendar-event-line-inner-container" style="background-color: ${CONFIG.color} !important; border-color: ${CONFIG.color} !important;">
                <div class="calendar-event-line-inner" style="display: flex !important; align-items: center !important;">
                    <div style="width: 6px !important; height: 6px !important; border-radius: 50% !important; background-color: ${CONFIG.dotColor} !important; flex-shrink: 0 !important; margin-right: 5px !important;"></div>
                    <span style="color: ${CONFIG.textColor} !important; flex: 1 !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important;">
                        <span title="${activity.type}: ${activity.title}">${activity.title}</span>
                    </span>
                    <span style="color: ${CONFIG.textColor} !important; flex-shrink: 0 !important; margin-left: 5px !important; font-size: 11px !important;">${activity.timeFrom}</span>
                </div>
            </div>
        `;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            openActivity(activity.id, activity.ownerType, activity.ownerId);
        });

        return el;
    }

    // Вид "Неделя" - блоки в сетке времени
    function createWeekBlockElement(activity, dayIndex, top, height, horizontalOffset, totalOverlapping) {
        // Рассчитываем ширину и позицию для перекрывающихся событий
        const baseLeft = dayIndex * 14.2857;
        const widthPercent = 14.2857 / totalOverlapping;
        const leftOffset = widthPercent * horizontalOffset;
        
        const el = document.createElement('div');
        el.className = 'calendar-event-block-wrap calendar-activity-item';
        el.dataset.activityId = activity.id;
        el.style.cssText = `
            position: absolute !important;
            top: ${top}px !important;
            height: ${height}px !important;
            left: calc(${baseLeft + leftOffset}% + 2px) !important;
            width: calc(${widthPercent}% - 4px) !important;
            z-index: ${100 + horizontalOffset} !important;
            cursor: pointer !important;
        `;

        el.innerHTML = `
            <div style="
                background-color: ${CONFIG.dotColor} !important; 
                border-radius: 4px !important; 
                padding: 2px 6px !important; 
                height: 100% !important; 
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
            ">
                <span style="color: #fff !important; font-size: 12px !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important;">
                    ${activity.title}
                </span>
                <span style="color: rgba(255,255,255,0.9) !important; font-size: 10px !important; white-space: nowrap !important;">
                    ${activity.timeFrom}–${activity.timeTo}
                </span>
            </div>
        `;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            openActivity(activity.id, activity.ownerType, activity.ownerId);
        });

        return el;
    }

    // Вид "День"
    function createDayElement(activity, top, height, horizontalOffset, totalOverlapping) {
        const widthPercent = 50 / totalOverlapping;
        const leftOffset = 50 + (widthPercent * horizontalOffset);
        
        const el = document.createElement('div');
        el.className = 'calendar-event-block-wrap calendar-activity-item';
        el.dataset.activityId = activity.id;
        el.style.cssText = `
            position: absolute !important;
            top: ${top}px !important;
            height: ${height}px !important;
            left: calc(${leftOffset}% + 2px) !important;
            width: calc(${widthPercent}% - 4px) !important;
            z-index: ${100 + horizontalOffset} !important;
            cursor: pointer !important;
        `;

        el.innerHTML = `
            <div style="
                background-color: ${CONFIG.dotColor} !important; 
                border-radius: 4px !important; 
                padding: 4px 8px !important; 
                height: 100% !important; 
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
            ">
                <span style="color: #fff !important; font-size: 12px !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important;">
                    ${activity.title}
                </span>
                <span style="color: rgba(255,255,255,0.9) !important; font-size: 11px !important;">
                    ${activity.timeFrom}–${activity.timeTo}
                </span>
            </div>
        `;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            openActivity(activity.id, activity.ownerType, activity.ownerId);
        });

        return el;
    }

    // ===================
    // Группировка перекрывающихся событий
    // ===================
    
    function groupOverlappingActivities(activities) {
        // Группируем по дате и времени
        const groups = {};
        
        activities.forEach(activity => {
            const key = `${activity.dateFrom}_${activity.timeFrom}`;
            if (!groups[key]) {
                groups[key] = [];
            }
            groups[key].push(activity);
        });
        
        // Добавляем информацию о позиции
        const result = [];
        Object.values(groups).forEach(group => {
            group.forEach((activity, idx) => {
                result.push({
                    ...activity,
                    horizontalOffset: idx,
                    totalOverlapping: group.length
                });
            });
        });
        
        return result;
    }

    // ===================
    // Вид "Месяц" (простой вариант)
    // ===================

    function renderMonth(activities) {
        const holder = document.querySelector('.calendar-grid-month-container .calendar-grid-month-events-holder');
        if (!holder) {
            console.log('[CalendarActivities] Month holder not found');
            return;
        }

        const range = getVisibleDateRange();
        const firstDayOfMonth = new Date(range.dateFrom);
        const firstDayWeekday = (firstDayOfMonth.getDay() || 7) - 1;

        // Находим максимальный top + height для каждой колонки (дня недели) в каждой строке
        const occupiedSpace = {}; // ключ: "weekIndex-dayIndex", значение: maxBottom
        
        holder.querySelectorAll('.calendar-event-line-wrap:not(.calendar-activity-item)').forEach(el => {
            const top = parseFloat(el.style.top) || 0;
            const left = el.style.left || '0';
            
            // Определяем dayIndex по left
            let dayIndex = 0;
            for (let i = 0; i < 7; i++) {
                const expectedLeft = i * 14.2857;
                if (left.includes(`${expectedLeft}%`) || left.includes(`calc(${expectedLeft}%`)) {
                    dayIndex = i;
                    break;
                }
                // Проверяем числовое значение
                const leftNum = parseFloat(left);
                if (!isNaN(leftNum) && Math.abs(leftNum - expectedLeft) < 2) {
                    dayIndex = i;
                    break;
                }
            }
            
            // Определяем weekIndex по top (примерно 100-120px на строку)
            const weekIndex = Math.floor(top / 100);
            
            const key = `${weekIndex}-${dayIndex}`;
            const bottom = top + CONFIG.lineHeight + 1;
            
            if (!occupiedSpace[key] || occupiedSpace[key] < bottom) {
                occupiedSpace[key] = bottom;
            }
        });
        
        console.log('[CalendarActivities] Occupied space:', occupiedSpace);

        // Группируем по дате
        const byDate = {};
        activities.forEach(a => {
            if (!byDate[a.dateFrom]) byDate[a.dateFrom] = [];
            byDate[a.dateFrom].push(a);
        });

        Object.keys(byDate).forEach(dateStr => {
            const date = parseDate(dateStr);
            const dayOfMonth = date.getDate();
            const dayIndex = (firstDayWeekday + dayOfMonth - 1) % 7;
            const weekIndex = Math.floor((firstDayWeekday + dayOfMonth - 1) / 7);
            
            const key = `${weekIndex}-${dayIndex}`;
            const startTop = occupiedSpace[key] || 0;
            
            byDate[dateStr].forEach((activity, idx) => {
                const topOffset = startTop + idx * (CONFIG.lineHeight + 1);
                const el = createMonthElement(activity, dayIndex, topOffset);
                holder.appendChild(el);
                
                // Обновляем занятое пространство
                occupiedSpace[key] = topOffset + CONFIG.lineHeight + 1;
            });
        });

        console.log('[CalendarActivities] Month rendered:', activities.length, 'activities');
    }

    function renderWeek(activities) {
        const holder = document.querySelector('.calendar-grid-week-container .calendar-grid-week-row > .calendar-grid-week-events-holder');
        if (!holder) {
            console.log('[CalendarActivities] Week holder not found');
            return;
        }

        const range = getVisibleDateRange();
        const weekStart = new Date(range.dateFrom);
        
        // Группируем перекрывающиеся
        const groupedActivities = groupOverlappingActivities(activities);

        groupedActivities.forEach(activity => {
            const activityDate = parseDate(activity.dateFrom);
            const dayIndex = Math.round((activityDate - weekStart) / (1000 * 60 * 60 * 24));
            
            if (dayIndex < 0 || dayIndex > 6) return;

            const [startH, startM] = activity.timeFrom.split(':').map(Number);
            const [endH, endM] = activity.timeTo.split(':').map(Number);
            
            const top = (startH * 60) + startM;
            const endTop = (endH * 60) + endM;
            const height = Math.max(endTop - top, 30);

            const el = createWeekBlockElement(
                activity, 
                dayIndex, 
                top, 
                height, 
                activity.horizontalOffset, 
                activity.totalOverlapping
            );
            holder.appendChild(el);
        });

        console.log('[CalendarActivities] Week rendered:', activities.length, 'activities');
    }

    // ===================
    // Главная функция (обновлённая)
    // ===================

    function injectActivities(forceDate, forceView) {
        // Удаляем старые наши элементы
        document.querySelectorAll('.calendar-activity-item').forEach(el => el.remove());

        let dateFrom, dateTo;
        let view = forceView || getCalendarView();
        
        console.log('[CalendarActivities] View:', view, '(forced:', !!forceView, ')');
        
        if (forceDate) {
            const date = forceDate instanceof Date ? forceDate : new Date(forceDate);
            
            if (view === 'day') {
                dateFrom = formatDate(date);
                dateTo = formatDate(date);
            } else if (view === 'week') {
                const day = date.getDay() || 7;
                const monday = new Date(date);
                monday.setDate(date.getDate() - day + 1);
                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);
                dateFrom = formatDate(monday);
                dateTo = formatDate(sunday);
            } else {
                dateFrom = formatDate(new Date(date.getFullYear(), date.getMonth(), 1));
                dateTo = formatDate(new Date(date.getFullYear(), date.getMonth() + 1, 0));
            }
            
            console.log('[CalendarActivities] Using forced date:', dateFrom, '-', dateTo);
        } else {
            const range = getVisibleDateRange();
            dateFrom = range.dateFrom;
            dateTo = range.dateTo;
            if (!forceView) {
                view = range.view;
            }
        }
        
        console.log('[CalendarActivities] Final - View:', view, '| Range:', dateFrom, '-', dateTo);
        
        if (!view) {
            console.log('[CalendarActivities] View not detected');
            return;
        }

        loadActivities(dateFrom, dateTo).then(activities => {
            console.log('[CalendarActivities] Loaded:', activities.length, 'activities');
            
            if (!activities || !activities.length) return;

            // Используем переданный view, не перепроверяем
            switch (view) {
                case 'month':
                    renderMonth(activities);
                    break;
                case 'week':
                    renderWeek(activities);
                    break;
                case 'day':
                    renderDay(activities, dateFrom);
                    break;
            }
        });
    }

    // ===================
    // Рендеринг дня (обновлённый)
    // ===================

    function renderDay(activities, forceDateFrom) {
        const holder = document.querySelector('.calendar-grid-day-container .calendar-grid-day-events-holder');
        if (!holder) {
            console.log('[CalendarActivities] Day holder not found');
            return;
        }

        // Используем переданную дату или берём из DOM
        let currentDayStr = forceDateFrom;
        
        if (!currentDayStr) {
            const dayCell = document.querySelector('.calendar-grid-day-container [data-bx-calendar-timeline-day]');
            if (dayCell) {
                const dateStr = dayCell.getAttribute('data-bx-calendar-timeline-day');
                const date = new Date(dateStr);
                currentDayStr = String(date.getDate()).padStart(2, '0') + '.' + 
                            String(date.getMonth() + 1).padStart(2, '0') + '.' + 
                            date.getFullYear();
            }
        }

        // Конвертируем формат даты если нужно (YYYY-MM-DD -> DD.MM.YYYY)
        if (currentDayStr && currentDayStr.includes('-')) {
            const [y, m, d] = currentDayStr.split('-');
            currentDayStr = `${d}.${m}.${y}`;
        }

        console.log('[CalendarActivities] Day filter date:', currentDayStr);

        // Фильтруем только дела на текущий день
        const dayActivities = activities.filter(a => {
            const match = !currentDayStr || a.dateFrom === currentDayStr;
            if (!match) {
                console.log('[CalendarActivities] Filtered out:', a.dateFrom, '!==', currentDayStr);
            }
            return match;
        });
        
        console.log('[CalendarActivities] Day activities after filter:', dayActivities.length);
        
        // Группируем перекрывающиеся
        const groupedActivities = groupOverlappingActivities(dayActivities);

        groupedActivities.forEach(activity => {
            const [startH, startM] = activity.timeFrom.split(':').map(Number);
            const [endH, endM] = activity.timeTo.split(':').map(Number);
            
            const top = (startH * 60) + startM;
            const endTop = (endH * 60) + endM;
            const height = Math.max(endTop - top, 30);

            const el = createDayElement(
                activity, 
                top, 
                height,
                activity.horizontalOffset,
                activity.totalOverlapping
            );
            holder.appendChild(el);
        });

        console.log('[CalendarActivities] Day rendered:', dayActivities.length, 'activities');
    }

    // ===================
    // Инициализация (исправленная v3)
    // ===================

    function init() {
        console.log('[CalendarActivities] Initializing...');
        
        let changeViewRangeTimer = null;
        let pendingDate = null;
        let viewChangeInProgress = false;
        let currentViewName = null;

        if (typeof BX !== 'undefined' && BX.addCustomEvent) {
            // Начало смены вида
            BX.addCustomEvent('beforesetview', function(params) {
                console.log('[CalendarActivities] beforesetview:', params.currentViewName, '->', params.newViewName);
                viewChangeInProgress = true;
                currentViewName = params.newViewName;
                
                if (changeViewRangeTimer) {
                    clearTimeout(changeViewRangeTimer);
                    changeViewRangeTimer = null;
                    pendingDate = null;
                }
            });
            
            // После смены вида
            BX.addCustomEvent('aftersetview', function(params) {
                const viewName = params.viewName || currentViewName;
                console.log('[CalendarActivities] aftersetview, viewName:', viewName);
                viewChangeInProgress = false;
                
                // Рендерим с принудительным видом, ждём пока Bitrix закончит
                setTimeout(() => injectActivities(null, viewName), 600);
            });
            
            // Смена диапазона дат
            BX.addCustomEvent('changeviewrange', function(newDate) {
                console.log('[CalendarActivities] changeviewrange:', newDate);
                
                pendingDate = newDate;
                
                if (changeViewRangeTimer) {
                    clearTimeout(changeViewRangeTimer);
                }
                
                changeViewRangeTimer = setTimeout(() => {
                    if (!viewChangeInProgress && pendingDate) {
                        console.log('[CalendarActivities] Processing changeviewrange');
                        // Передаём текущий вид если известен
                        injectActivities(pendingDate, currentViewName);
                    }
                    changeViewRangeTimer = null;
                    pendingDate = null;
                }, 200);
            });
            
            // После AJAX загрузки событий Bitrix - перерисовываем наши
            BX.addCustomEvent('onajaxsuccessfinish', function() {
                // Debounce - ждём пока все AJAX завершатся
                clearTimeout(window._calendarAjaxTimer);
                window._calendarAjaxTimer = setTimeout(() => {
                    // Проверяем что наши элементы пропали
                    if (document.querySelectorAll('.calendar-activity-item').length === 0) {
                        console.log('[CalendarActivities] Re-inject after AJAX');
                        injectActivities(null, currentViewName);
                    }
                }, 300);
            });
            
            console.log('[CalendarActivities] BX events subscribed');
        }

        // Первичная загрузка
        setTimeout(() => {
            if (document.querySelectorAll('.calendar-activity-item').length === 0) {
                // Определяем начальный вид
                currentViewName = getCalendarView();
                console.log('[CalendarActivities] Initial load, view:', currentViewName);
                injectActivities(null, currentViewName);
            }
        }, 10000);

        console.log('[CalendarActivities] Initialized');
    }

    // Запуск
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Экспорт
    window.CalendarActivities = {
        refresh: injectActivities,
        getRange: getVisibleDateRange,
        getView: getCalendarView
    };
    


})();