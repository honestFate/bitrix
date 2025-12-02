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
        // "dd.mm.yyyy" -> Date
        const [d, m, y] = dateStr.split('.');
        return new Date(y, m - 1, d);
    }

    function loadActivities(dateFrom, dateTo) {
        const url = `${CONFIG.ajaxUrl}?date_from=${dateFrom}&date_to=${dateTo}`;
        return fetch(url).then(r => r.json()).catch(() => []);
    }

    function openActivity(activityId, ownerType, ownerId) {
        // Открываем карточку сущности CRM со слайдером
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
    // Определение вида календаря
    // ===================

    function getCalendarView() {
        if (document.querySelector('.calendar-grid-month-container')) return 'month';
        if (document.querySelector('.calendar-grid-week-container')) return 'week';
        if (document.querySelector('.calendar-grid-day')) return 'day';
        return null;
    }

    function getVisibleDateRange() {
        const view = getCalendarView();
        let dateFrom, dateTo;

        // Пробуем получить из Bitrix Calendar API
        if (window.BX && BX.Calendar && BX.Calendar.Get) {
            const calendar = BX.Calendar.Get();
            if (calendar && calendar.currentViewDate) {
                const viewDate = calendar.currentViewDate;
                
                if (view === 'month') {
                    dateFrom = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
                    dateTo = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
                } else if (view === 'week') {
                    const day = viewDate.getDay() || 7;
                    dateFrom = new Date(viewDate);
                    dateFrom.setDate(viewDate.getDate() - day + 1);
                    dateTo = new Date(dateFrom);
                    dateTo.setDate(dateFrom.getDate() + 6);
                } else {
                    dateFrom = new Date(viewDate);
                    dateTo = new Date(viewDate);
                }
                
                return { dateFrom: formatDate(dateFrom), dateTo: formatDate(dateTo), view };
            }
        }

        // Fallback: парсим из DOM
        const todayCell = document.querySelector('.calendar-grid-today');
        if (todayCell) {
            const attr = todayCell.getAttribute('data-bx-calendar-timeline-day');
            if (attr) {
                const today = new Date(attr);
                if (view === 'month') {
                    dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
                    dateTo = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                } else if (view === 'week') {
                    const day = today.getDay() || 7;
                    dateFrom = new Date(today);
                    dateFrom.setDate(today.getDate() - day + 1);
                    dateTo = new Date(dateFrom);
                    dateTo.setDate(dateFrom.getDate() + 6);
                } else {
                    dateFrom = today;
                    dateTo = today;
                }
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
    // Создание элементов для разных видов
    // ===================

    // Вид "Месяц" - линейные события
    function createMonthElement(activity, dayIndex, weekRow, topOffset) {
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
            <div class="calendar-event-line-inner-container" style="background-color: ${CONFIG.color}; border-color: ${CONFIG.color};">
                <div class="calendar-event-line-inner">
                    <div class="calendar-event-line-dot" style="background-color: ${CONFIG.dotColor};"></div>
                    <span class="calendar-event-line-time" style="color: ${CONFIG.textColor};">${activity.timeFrom}</span>
                    <span class="calendar-event-line-text" style="color: ${CONFIG.textColor};">
                        <span title="${activity.type}: ${activity.title}">${activity.title}</span>
                    </span>
                </div>
            </div>
        `;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            openActivity(activity.id, activity.ownerType, activity.ownerId);
        });

        return el;
    }

    // Вид "Неделя" - линейные события (шапка) + блоки (сетка)
    function createWeekLineElement(activity, dayIndex, topOffset) {
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
            <div class="calendar-event-line-inner-container" style="background-color: ${CONFIG.color}; border-color: ${CONFIG.color};">
                <div class="calendar-event-line-inner">
                    <div class="calendar-event-line-dot" style="background-color: ${CONFIG.dotColor};"></div>
                    <span class="calendar-event-line-text" style="color: ${CONFIG.textColor};">
                        <span title="${activity.type}: ${activity.title}">${activity.title}</span>
                    </span>
                </div>
            </div>
        `;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            openActivity(activity.id, activity.ownerType, activity.ownerId);
        });

        return el;
    }

    function createWeekBlockElement(activity, dayIndex, startHour, endHour, workdayStart) {
        const top = (startHour - workdayStart) * CONFIG.hourHeight;
        const height = Math.max((endHour - startHour) * CONFIG.hourHeight, 25);

        const el = document.createElement('div');
        el.className = 'calendar-event-block-wrap calendar-activity-item';
        el.dataset.activityId = activity.id;
        el.style.cssText = `
            position: absolute;
            top: ${top}px;
            height: ${height}px;
            left: calc(${dayIndex * 14.2857}% + 2px);
            width: calc(14.2857% - 6px);
            z-index: 100;
            cursor: pointer;
        `;

        el.innerHTML = `
            <div class="calendar-event-block-inner" style="background-color: ${CONFIG.dotColor}; border-radius: 4px; padding: 2px 5px; height: 100%; overflow: hidden;">
                <span class="calendar-event-block-text" style="color: #fff; font-size: 11px;">
                    ${activity.timeFrom} ${activity.title}
                </span>
            </div>
        `;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            openActivity(activity.id, activity.ownerType, activity.ownerId);
        });

        return el;
    }

    // Вид "День" - блоки
    function createDayElement(activity, startHour, endHour, workdayStart) {
        const top = (startHour - workdayStart) * CONFIG.hourHeight;
        const height = Math.max((endHour - startHour) * CONFIG.hourHeight, 25);

        const el = document.createElement('div');
        el.className = 'calendar-event-block-wrap calendar-activity-item';
        el.dataset.activityId = activity.id;
        el.style.cssText = `
            position: absolute;
            top: ${top}px;
            height: ${height}px;
            left: calc(50% + 2px);
            width: calc(50% - 4px);
            z-index: 100;
            cursor: pointer;
        `;

        el.innerHTML = `
            <div class="calendar-event-block-inner" style="background-color: ${CONFIG.dotColor}; border-radius: 4px; padding: 4px 8px; height: 100%; overflow: hidden;">
                <span class="calendar-event-block-title" style="color: #fff;">
                    <span class="calendar-event-block-text">${activity.title}</span>
                </span>
                <span class="calendar-event-block-time" style="color: rgba(255,255,255,0.9); font-size: 11px;">
                    ${activity.timeFrom} – ${activity.timeTo}
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
    // Рендеринг для каждого вида
    // ===================

    function renderMonth(activities) {
        const holder = document.querySelector('.calendar-grid-month-events-holder');
        if (!holder) return;

        // Получаем первый день месяца из DOM
        const firstCell = document.querySelector('[data-bx-calendar-month]');
        const range = getVisibleDateRange();
        const firstDayOfMonth = new Date(range.dateFrom);
        const firstDayWeekday = (firstDayOfMonth.getDay() || 7) - 1; // 0 = пн

        // Группируем по датам
        const byDate = {};
        activities.forEach(a => {
            if (!byDate[a.dateFrom]) byDate[a.dateFrom] = [];
            byDate[a.dateFrom].push(a);
        });

        Object.keys(byDate).forEach(dateStr => {
            const date = parseDate(dateStr);
            const dayOfMonth = date.getDate();
            const dayIndex = (firstDayWeekday + dayOfMonth - 1) % 7;
            const weekRow = Math.floor((firstDayWeekday + dayOfMonth - 1) / 7);

            // Считаем существующие события на этот день
            const existingCount = holder.querySelectorAll(
                `[data-bx-calendar-entry*="|${dateStr}"]`
            ).length;

            byDate[dateStr].forEach((activity, idx) => {
                const topOffset = (existingCount + idx) * (CONFIG.lineHeight + 1);
                const el = createMonthElement(activity, dayIndex, weekRow, topOffset);
                holder.appendChild(el);
            });
        });
    }

    function renderWeek(activities) {
        // Линейные события в шапке
        const lineHolder = document.querySelector('.calendar-grid-week-events-holder');
        // Блоки в сетке
        const gridHolder = document.querySelector('.calendar-grid-week-events-wrap, .calendar-grid-week-row');
        
        const range = getVisibleDateRange();
        const weekStart = new Date(range.dateFrom);
        const workdayStart = 9; // Начало рабочего дня

        activities.forEach(activity => {
            const activityDate = parseDate(activity.dateFrom);
            const dayIndex = Math.floor((activityDate - weekStart) / (1000 * 60 * 60 * 24));
            
            if (dayIndex < 0 || dayIndex > 6) return;

            const [startH, startM] = activity.timeFrom.split(':').map(Number);
            const [endH, endM] = activity.timeTo.split(':').map(Number);
            const startHour = startH + startM / 60;
            const endHour = endH + endM / 60;

            // События на весь день или короткие - в шапку
            if (activity.isAllDay || (endHour - startHour) <= 0.5) {
                if (lineHolder) {
                    const existingCount = lineHolder.querySelectorAll(
                        `.calendar-activity-item[data-date="${activity.dateFrom}"]`
                    ).length + lineHolder.querySelectorAll(
                        `[data-bx-calendar-entry*="|${activity.dateFrom}"]`
                    ).length;
                    
                    const el = createWeekLineElement(activity, dayIndex, existingCount * CONFIG.lineHeight);
                    el.dataset.date = activity.dateFrom;
                    lineHolder.appendChild(el);
                }
            } else {
                // Остальные - блоками в сетку
                // Ищем контейнер для блоков
                const dayColumns = document.querySelectorAll('.calendar-grid-week-cell-events-holder');
                if (dayColumns[dayIndex]) {
                    const el = createDayElement(activity, startHour, endHour, workdayStart);
                    el.style.left = '2px';
                    el.style.width = 'calc(100% - 4px)';
                    dayColumns[dayIndex].appendChild(el);
                } else if (gridHolder) {
                    const el = createWeekBlockElement(activity, dayIndex, startHour, endHour, workdayStart);
                    gridHolder.appendChild(el);
                }
            }
        });
    }

    function renderDay(activities) {
        const holder = document.querySelector('.calendar-grid-day-events-holder');
        if (!holder) return;

        const workdayStart = 9;

        activities.forEach(activity => {
            const [startH, startM] = activity.timeFrom.split(':').map(Number);
            const [endH, endM] = activity.timeTo.split(':').map(Number);
            const startHour = startH + startM / 60;
            const endHour = endH + endM / 60;

            const el = createDayElement(activity, startHour, endHour, workdayStart);
            holder.appendChild(el);
        });
    }

    // ===================
    // Главная функция
    // ===================

    function injectActivities() {
        // Удаляем старые
        document.querySelectorAll('.calendar-activity-item').forEach(el => el.remove());

        const { dateFrom, dateTo, view } = getVisibleDateRange();
        if (!view) return;

        loadActivities(dateFrom, dateTo).then(activities => {
            if (!activities || !activities.length) return;

            switch (view) {
                case 'month':
                    renderMonth(activities);
                    break;
                case 'week':
                    renderWeek(activities);
                    break;
                case 'day':
                    renderDay(activities);
                    break;
            }
        });
    }

    // ===================
    // Инициализация
    // ===================

    function init() {
        // Первый запуск с задержкой (ждём загрузки календаря)
        setTimeout(injectActivities, 800);

        // Наблюдаем за изменениями DOM (смена вида/месяца/недели)
        const calendarContainer = document.querySelector('.calendar-wrap, .calendar-slider-calendar-wrap, #calendar');
        if (calendarContainer) {
            let debounceTimer;
            const observer = new MutationObserver(() => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(injectActivities, 300);
            });

            observer.observe(calendarContainer, { 
                childList: true, 
                subtree: true,
                attributes: true,
                attributeFilter: ['class']
            });
        }

        // Также слушаем клики по навигации календаря
        document.addEventListener('click', (e) => {
            if (e.target.closest('.calendar-nav, .calendar-grid-week-nav, .calendar-view-switcher')) {
                setTimeout(injectActivities, 500);
            }
        });
    }

    // Запуск
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Экспорт для отладки
    window.CalendarActivities = {
        refresh: injectActivities,
        getRange: getVisibleDateRange
    };

})();