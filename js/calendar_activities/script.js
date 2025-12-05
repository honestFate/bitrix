(function() {
    'use strict';

    /**
     * Интеграция CRM активностей в календарь Bitrix24
     * Использует нативный API календаря через entriesRaw
     */

    const CONFIG = {
        ajaxUrl: '/local/ajax/calendar_activities.php',
        sectionId: '4', // ID секции календаря (можно изменить)
        color: '#FF9800',
        textColor: '#FFFFFF',
        entryPrefix: 'crm_activity_',
        debug: true
    };

    function log(...args) {
        if (CONFIG.debug) {
            console.log('[CRM Calendar]', ...args);
        }
    }

    // ===================
    // Состояние
    // ===================

    const state = {
        calendar: null,
        initialized: false,
        loadedActivities: new Map(),
        currentDateFrom: null,
        currentDateTo: null
    };

    // ===================
    // Утилиты
    // ===================

    function getCalendar() {
        if (state.calendar) return state.calendar;
        
        if (window.BXEventCalendar?.instances) {
            const keys = Object.keys(window.BXEventCalendar.instances);
            if (keys.length > 0) {
                state.calendar = window.BXEventCalendar.instances[keys[0]];
                return state.calendar;
            }
        }
        return null;
    }

    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function formatBxDate(date) {
        const d = String(date.getDate()).padStart(2, '0');
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const y = date.getFullYear();
        const h = String(date.getHours()).padStart(2, '0');
        const min = String(date.getMinutes()).padStart(2, '0');
        return `${d}.${m}.${y} ${h}:${min}:00`;
    }

    function parseActivityDate(dateStr, timeStr) {
        // dateStr: "DD.MM.YYYY", timeStr: "HH:MM"
        const [d, m, y] = dateStr.split('.');
        const [h, min] = (timeStr || '00:00').split(':');
        return new Date(y, m - 1, d, h || 0, min || 0);
    }

    // ===================
    // Загрузка активностей
    // ===================

    function loadActivities(dateFrom, dateTo) {
        const url = `${CONFIG.ajaxUrl}?date_from=${dateFrom}&date_to=${dateTo}`;
        log('Loading activities:', url);
        
        return fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    console.error('[CRM Calendar] API error:', data.error);
                    return [];
                }
                log('Loaded', data.length, 'activities');
                return data;
            })
            .catch(err => {
                console.error('[CRM Calendar] Fetch error:', err);
                return [];
            });
    }

    // ===================
    // Конвертация активности в формат entriesRaw
    // ===================

    function activityToRawEntry(activity, cal) {
        const dateFrom = parseActivityDate(activity.dateFrom, activity.timeFrom);
        const dateTo = parseActivityDate(activity.dateTo, activity.timeTo);
        
        // Если время окончания <= времени начала, добавляем час
        if (dateTo <= dateFrom) {
            dateTo.setTime(dateFrom.getTime() + 3600000);
        }

        const ownerId = cal.util?.config?.ownerId || 1;
        const userId = cal.util?.config?.userId || 1;

        return {
            // Основные идентификаторы
            ID: CONFIG.entryPrefix + activity.id,
            PARENT_ID: CONFIG.entryPrefix + activity.id,
            
            // Статус
            ACTIVE: 'Y',
            DELETED: 'N',
            
            // Тип и владелец
            CAL_TYPE: 'user',
            OWNER_ID: String(ownerId),
            
            // Название и описание
            NAME: activity.title || activity.type || 'CRM Activity',
            DESCRIPTION: activity.description || '',
            
            // Даты в формате Bitrix
            DATE_FROM: formatBxDate(dateFrom),
            DATE_TO: formatBxDate(dateTo),
            DATE_FROM_TS_UTC: String(Math.floor(dateFrom.getTime() / 1000)),
            DATE_TO_TS_UTC: String(Math.floor(dateTo.getTime() / 1000)),
            DT_LENGTH: Math.floor((dateTo - dateFrom) / 1000),
            
            // Временная зона
            TZ_FROM: 'Europe/Moscow',
            TZ_TO: 'Europe/Moscow',
            TZ_OFFSET_FROM: '10800',
            TZ_OFFSET_TO: '10800',
            
            // Тип события
            DT_SKIP_TIME: activity.isAllDay ? 'Y' : 'N',
            
            // Секция и цвет
            SECT_ID: CONFIG.sectionId,
            SECTION_ID: CONFIG.sectionId,
            COLOR: CONFIG.color,
            TEXT_COLOR: CONFIG.textColor,
            
            // Параметры встречи
            ACCESSIBILITY: 'busy',
            IMPORTANCE: 'normal',
            PRIVATE_EVENT: '',
            IS_MEETING: false,
            MEETING_STATUS: 'Y',
            RRULE: '',
            ATTENDEES_CODES: [],
            
            // Автор
            CREATED_BY: String(userId),
            
            // Разрешения
            permissions: {
                edit: false,
                edit_attendees: false,
                edit_location: false
            },
            
            // Дополнительные данные для обработки клика
            _isCrmActivity: true,
            _activityId: activity.id,
            _ownerType: activity.ownerType,
            _ownerId: activity.ownerId
        };
    }

    // ===================
    // Добавление активностей в календарь
    // ===================

    function injectActivities(activities) {
        const cal = getCalendar();
        if (!cal) {
            log('Calendar not found');
            return;
        }

        const view = cal.getView();
        if (!view) {
            log('View not found');
            return;
        }

        log('Injecting', activities.length, 'activities into', view.name, 'view');

        // Удаляем старые CRM активности из entriesRaw
        if (cal.entryController?.entriesRaw) {
            cal.entryController.entriesRaw = cal.entryController.entriesRaw.filter(
                e => !String(e.ID).startsWith(CONFIG.entryPrefix)
            );
        }

        // Конвертируем и добавляем новые
        const rawEntries = activities.map(a => activityToRawEntry(a, cal));
        
        if (rawEntries.length > 0 && cal.entryController?.appendToEntriesRaw) {
            cal.entryController.appendToEntriesRaw(rawEntries);
            log('Added', rawEntries.length, 'entries to entriesRaw');
        }

        // Пересоздаём entries и перерисовываем
        refreshView();
    }

    function refreshView() {
        const cal = getCalendar();
        if (!cal) return;

        const view = cal.getView();
        if (!view) return;

        try {
            // Пересоздаём Entry объекты из сырых данных
            if (cal.entryController?.getEntriesFromEntriesRaw) {
                const entries = cal.entryController.getEntriesFromEntriesRaw();
                if (entries) {
                    view.entries = entries;
                    log('Updated view.entries:', entries.length);
                }
            }

            // Перерисовываем
            if (view.redraw) {
                view.redraw();
                log('View redrawn');
            }
        } catch (e) {
            console.error('[CRM Calendar] Refresh error:', e);
        }
    }

    // ===================
    // Получение диапазона дат
    // ===================

    function getDateRange() {
        const cal = getCalendar();
        if (!cal) return null;

        const viewName = cal.currentViewName;
        const viewDate = cal.viewRangeDate || new Date();

        let dateFrom, dateTo;

        if (viewName === 'day') {
            dateFrom = new Date(viewDate);
            dateTo = new Date(viewDate);
        } else if (viewName === 'week') {
            const day = viewDate.getDay() || 7;
            dateFrom = new Date(viewDate);
            dateFrom.setDate(viewDate.getDate() - day + 1);
            dateTo = new Date(dateFrom);
            dateTo.setDate(dateFrom.getDate() + 6);
        } else {
            // month или list
            dateFrom = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
            dateTo = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
        }

        return {
            dateFrom: formatDate(dateFrom),
            dateTo: formatDate(dateTo),
            view: viewName
        };
    }

    // ===================
    // Главная функция обновления
    // ===================

    function update() {
        const range = getDateRange();
        if (!range) {
            log('Could not get date range');
            return;
        }

        // Проверяем не загружали ли уже этот диапазон
        const rangeKey = `${range.dateFrom}_${range.dateTo}`;
        if (state.currentDateFrom === range.dateFrom && state.currentDateTo === range.dateTo) {
            log('Range already loaded, refreshing view only');
            refreshView();
            return;
        }

        state.currentDateFrom = range.dateFrom;
        state.currentDateTo = range.dateTo;

        log('Updating for range:', range);

        loadActivities(range.dateFrom, range.dateTo)
            .then(activities => {
                if (activities && activities.length > 0) {
                    injectActivities(activities);
                } else {
                    // Удаляем старые если нет новых
                    injectActivities([]);
                }
            });
    }

    // ===================
    // Обработка клика на активность
    // ===================

    function openActivitySlider(activityId) {
        // Открываем слайдер просмотра/редактирования активности CRM
        const url = `/crm/activity/?act=view&id=${activityId}`;
        
        if (typeof BX !== 'undefined' && BX.CrmActivityEditor) {
            // Используем нативный редактор активностей CRM
            BX.CrmActivityEditor.viewActivity(activityId);
            return true;
        }
        
        if (typeof BX !== 'undefined' && BX.Crm?.Activity?.TodoEditor) {
            // Bitrix24 новый редактор дел
            BX.Crm.Activity.TodoEditor.open({ activityId: activityId });
            return true;
        }

        if (typeof BX !== 'undefined' && BX.SidePanel?.Instance) {
            // Fallback - открываем в слайдере
            BX.SidePanel.Instance.open(url, { width: 700 });
            return true;
        }
        
        // Последний fallback
        window.open(url, '_blank');
        return true;
    }

    function openOwnerCard(ownerType, ownerId) {
        const urls = {
            lead: `/crm/lead/details/${ownerId}/`,
            deal: `/crm/deal/details/${ownerId}/`,
            contact: `/crm/contact/details/${ownerId}/`,
            company: `/crm/company/details/${ownerId}/`
        };
        const url = urls[ownerType] || urls.deal;

        if (typeof BX !== 'undefined' && BX.SidePanel?.Instance) {
            BX.SidePanel.Instance.open(url, { width: 1000 });
        } else {
            window.open(url, '_blank');
        }
    }

    function handleCrmActivityClick(entry, event) {
        if (!entry?.data?._isCrmActivity) {
            return false;
        }

        const data = entry.data;
        log('CRM activity clicked:', data._activityId, data);

        // Предотвращаем стандартное поведение календаря
        if (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
        }

        // Открываем активность
        openOwnerCard(data._ownerType, data._ownerId);

        return true;
    }

    function findEntryByElement(element) {
        const cal = getCalendar();
        if (!cal) return null;

        const view = cal.getView();
        if (!view?.entries) return null;

        // Ищем ID события в атрибутах или родителях
        let entryWrap = element.closest('[data-bx-calendar-entry]');
        if (!entryWrap) {
            entryWrap = element.closest('.calendar-event-block-wrap');
        }
        if (!entryWrap) {
            entryWrap = element.closest('.calendar-grid-month-event-slot');
        }

        if (!entryWrap) return null;

        // Пробуем получить ID из атрибута
        let entryId = entryWrap.getAttribute('data-bx-calendar-entry');
        
        // Или из data-entry-id
        if (!entryId) {
            entryId = entryWrap.dataset.entryId;
        }

        // Ищем по ID в entries
        if (entryId) {
            const entry = view.entries.find(e => 
                String(e.id) === String(entryId) || 
                e.uid === entryId
            );
            if (entry) return entry;
        }

        // Fallback: ищем по уникальным данным в элементе
        // Проверяем текст названия
        const titleEl = entryWrap.querySelector('.calendar-event-block-title, .calendar-item-content-name');
        if (titleEl) {
            const title = titleEl.textContent.trim();
            const entry = view.entries.find(e => 
                e.name === title && e.data?._isCrmActivity
            );
            if (entry) return entry;
        }

        return null;
    }

    // ===================
    // Инициализация
    // ===================

    function init() {
        log('Initializing...');

        if (typeof BX === 'undefined') {
            log('BX not found, waiting...');
            setTimeout(init, 500);
            return;
        }

        // Ждём появления календаря
        const cal = getCalendar();
        if (!cal) {
            log('Calendar not found, subscribing to event...');
            
            BX.addCustomEvent('oncalendarafterbuildviews', function(calendar) {
                log('Calendar found via event');
                state.calendar = calendar;
                setupEventHandlers();
                update();
            });
            return;
        }

        setupEventHandlers();
        
        // Первичная загрузка с небольшой задержкой
        setTimeout(update, 500);

        state.initialized = true;
        log('Initialized');
    }

    function setupEventHandlers() {
        if (typeof BX === 'undefined') return;

        // Смена диапазона дат
        BX.addCustomEvent('changeviewrange', function(newDate) {
            log('changeviewrange:', newDate);
            // Сбрасываем кэш диапазона
            state.currentDateFrom = null;
            state.currentDateTo = null;
            // Обновляем с задержкой чтобы календарь успел обновиться
            setTimeout(update, 300);
        });

        // Смена вида (день/неделя/месяц)
        BX.addCustomEvent('aftersetview', function(params) {
            log('aftersetview:', params);
            state.currentDateFrom = null;
            state.currentDateTo = null;
            setTimeout(update, 300);
        });

        // После AJAX загрузки событий Bitrix
        BX.addCustomEvent('BX.Calendar:onEntryListReload', function() {
            log('onEntryListReload - refreshing');
            setTimeout(update, 200);
        });

        // ==========================================
        // ПЕРЕХВАТ КЛИКА НА CRM АКТИВНОСТИ
        // ==========================================

        // Способ 1: Перехватываем событие viewonclick
        BX.addCustomEvent('viewonclick', function(params) {
            if (!params || !params[0]) return;
            
            const eventData = params[0];
            const target = eventData.target || eventData.e?.target;
            
            if (!target) return;

            // Проверяем, является ли это CRM активностью
            const entry = findEntryByElement(target);
            if (entry?.data?._isCrmActivity) {
                log('viewonclick: CRM activity detected, intercepting');
                handleCrmActivityClick(entry, eventData.e);
            }
        });

        // Способ 2: Перехват на уровне Entry
        BX.addCustomEvent('BX.Calendar:onEntryClick', function(params) {
            if (!params) return;
            
            const entry = params.entry || params;
            if (entry?.data?._isCrmActivity) {
                log('onEntryClick: CRM activity detected');
                handleCrmActivityClick(entry, params.event);
            }
        });

        // Способ 3: Переопределяем handleEntryClick на каждом view
        const cal = getCalendar();
        if (cal?.views) {
            cal.views.forEach(view => {
                if (view.handleEntryClick) {
                    const originalHandleEntryClick = view.handleEntryClick.bind(view);
                    view.handleEntryClick = function(params) {
                        const entry = params?.entry;
                        if (entry?.data?._isCrmActivity) {
                            log('handleEntryClick intercepted for CRM activity');
                            handleCrmActivityClick(entry, params?.event);
                            return; // Не вызываем оригинальный обработчик
                        }
                        return originalHandleEntryClick(params);
                    };
                }
                
                // Также переопределяем showCompactViewForm
                if (view.showCompactViewForm) {
                    const originalShowCompactViewForm = view.showCompactViewForm.bind(view);
                    view.showCompactViewForm = function(params) {
                        const entry = params?.entry;
                        if (entry?.data?._isCrmActivity) {
                            log('showCompactViewForm intercepted for CRM activity');
                            handleCrmActivityClick(entry, null);
                            return;
                        }
                        return originalShowCompactViewForm(params);
                    };
                }
            });
            log('View handlers patched');
        }

        // Способ 4: Делегирование событий на контейнере календаря (наиболее надёжный)
        const cal2 = getCalendar();
        if (cal2?.mainCont) {
            cal2.mainCont.addEventListener('click', function(e) {
                const target = e.target;
                
                // Проверяем клик по элементу события
                const eventElement = target.closest('.calendar-event-block-wrap, .calendar-grid-month-event-slot, [data-bx-calendar-entry]');
                if (!eventElement) return;

                const entry = findEntryByElement(eventElement);
                if (entry?.data?._isCrmActivity) {
                    log('DOM click intercepted for CRM activity');
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    handleCrmActivityClick(entry, e);
                }
            }, true); // Используем capture phase для перехвата до обработчиков календаря
            
            log('DOM click handler attached');
        }

        log('Event handlers set up');
    }

    // ===================
    // Запуск
    // ===================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Даём время на инициализацию календаря
        setTimeout(init, 1000);
    }

    // ===================
    // Экспорт API
    // ===================

    window.CRMCalendar = {
        update: update,
        refresh: refreshView,
        getState: () => ({ ...state }),
        
        // Ручное добавление активности для тестирования
        addTest: function() {
            const now = new Date();
            const testActivity = {
                id: 'test_' + Date.now(),
                title: '🟠 Test CRM Activity',
                type: 'Дело',
                dateFrom: now.toLocaleDateString('ru-RU').replace(/\//g, '.'),
                dateTo: now.toLocaleDateString('ru-RU').replace(/\//g, '.'),
                timeFrom: now.toTimeString().slice(0, 5),
                timeTo: new Date(now.getTime() + 3600000).toTimeString().slice(0, 5),
                ownerType: 'deal',
                ownerId: 1
            };
            
            const cal = getCalendar();
            if (cal) {
                const rawEntry = activityToRawEntry(testActivity, cal);
                cal.entryController?.appendToEntriesRaw?.([rawEntry]);
                refreshView();
                log('Test activity added');
                return testActivity;
            }
            return null;
        },
        
        // Очистка CRM активностей
        clear: function() {
            const cal = getCalendar();
            if (cal?.entryController?.entriesRaw) {
                cal.entryController.entriesRaw = cal.entryController.entriesRaw.filter(
                    e => !String(e.ID).startsWith(CONFIG.entryPrefix)
                );
                refreshView();
                log('CRM activities cleared');
            }
        },
        
        // Открыть активность по ID
        openActivity: function(activityId) {
            return openActivitySlider(activityId);
        },
        
        // Тест клика
        testClick: function() {
            const cal = getCalendar();
            const view = cal?.getView();
            if (view?.entries) {
                const crmEntry = view.entries.find(e => e.data?._isCrmActivity);
                if (crmEntry) {
                    log('Found CRM entry:', crmEntry);
                    handleCrmActivityClick(crmEntry, null);
                    return crmEntry;
                }
                log('No CRM entries found');
            }
            return null;
        }
    };

    log('Script loaded');

})();