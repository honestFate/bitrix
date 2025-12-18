(function() {
    'use strict';

    /**
     * Интеграция CRM активностей в календарь Bitrix24
     */

    const CONFIG = {
        ajaxUrl: '/local/ajax/calendar_activities.php',
        sectionId: '4',
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

    const state = {
        calendar: null,
        initialized: false,
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
        const [d, m, y] = dateStr.split('.');
        const [h, min] = (timeStr || '00:00').split(':');
        return new Date(y, m - 1, d, h || 0, min || 0);
    }

    function isCrmEntry(entry) {
        if (!entry) return false;
        if (entry.data?._isCrmActivity) return true;
        if (String(entry.id).startsWith(CONFIG.entryPrefix)) return true;
        if (String(entry.uid).startsWith(CONFIG.entryPrefix)) return true;
        return false;
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
    // Конвертация активности
    // ===================

    function activityToRawEntry(activity, cal) {
        const dateFrom = parseActivityDate(activity.dateFrom, activity.timeFrom);
        const dateTo = parseActivityDate(activity.dateTo, activity.timeTo);
        
        if (dateTo <= dateFrom) {
            dateTo.setTime(dateFrom.getTime() + 3600000);
        }

        const ownerId = cal.util?.config?.ownerId || 1;
        const userId = cal.util?.config?.userId || 1;

        return {
            ID: CONFIG.entryPrefix + activity.id,
            PARENT_ID: CONFIG.entryPrefix + activity.id,
            ACTIVE: 'Y',
            DELETED: 'N',
            CAL_TYPE: 'user',
            OWNER_ID: String(ownerId),
            NAME: activity.title || activity.type || 'CRM Activity',
            DESCRIPTION: activity.description || '',
            DATE_FROM: formatBxDate(dateFrom),
            DATE_TO: formatBxDate(dateTo),
            DATE_FROM_TS_UTC: String(Math.floor(dateFrom.getTime() / 1000)),
            DATE_TO_TS_UTC: String(Math.floor(dateTo.getTime() / 1000)),
            DT_LENGTH: Math.floor((dateTo - dateFrom) / 1000),
            TZ_FROM: 'Europe/Moscow',
            TZ_TO: 'Europe/Moscow',
            TZ_OFFSET_FROM: '10800',
            TZ_OFFSET_TO: '10800',
            DT_SKIP_TIME: activity.isAllDay ? 'Y' : 'N',
            SECT_ID: CONFIG.sectionId,
            SECTION_ID: CONFIG.sectionId,
            COLOR: CONFIG.color,
            TEXT_COLOR: CONFIG.textColor,
            ACCESSIBILITY: 'busy',
            IMPORTANCE: 'normal',
            PRIVATE_EVENT: '',
            IS_MEETING: false,
            MEETING_STATUS: 'Y',
            RRULE: '',
            ATTENDEES_CODES: [],
            CREATED_BY: String(userId),
            _isCrmActivity: true,
            _activityId: activity.id,
            _ownerType: activity.ownerType,
            _ownerId: activity.ownerId
        };
    }

    // ===================
    // Инъекция и обновление
    // ===================

    function injectActivities(activities) {
        const cal = getCalendar();
        if (!cal) return;

        const view = cal.getView();
        if (!view) return;

        log('Injecting', activities.length, 'activities');

        if (cal.entryController?.entriesRaw) {
            cal.entryController.entriesRaw = cal.entryController.entriesRaw.filter(
                e => !String(e.ID).startsWith(CONFIG.entryPrefix)
            );
        }

        const rawEntries = activities.map(a => activityToRawEntry(a, cal));
        
        if (rawEntries.length > 0 && cal.entryController?.appendToEntriesRaw) {
            cal.entryController.appendToEntriesRaw(rawEntries);
        }

        refreshView();
    }

    function refreshView() {
        const cal = getCalendar();
        if (!cal) return;

        const view = cal.getView();
        if (!view) return;

        try {
            if (cal.entryController?.getEntriesFromEntriesRaw) {
                const entries = cal.entryController.getEntriesFromEntriesRaw();
                if (entries) {
                    view.entries = entries;
                    log('Updated view.entries:', entries.length);
                }
            }

            if (view.redraw) {
                view.redraw();
                log('View redrawn');
            }
        } catch (e) {
            console.error('[CRM Calendar] Refresh error:', e);
        }
    }

    // ===================
    // Диапазон дат
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
            dateFrom = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
            dateTo = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
        }

        return { dateFrom: formatDate(dateFrom), dateTo: formatDate(dateTo), view: viewName };
    }

    function update() {
        const range = getDateRange();
        if (!range) return;

        if (state.currentDateFrom === range.dateFrom && state.currentDateTo === range.dateTo) {
            refreshView();
            return;
        }

        state.currentDateFrom = range.dateFrom;
        state.currentDateTo = range.dateTo;
        log('Updating for range:', range);

        loadActivities(range.dateFrom, range.dateTo).then(activities => {
            injectActivities(activities || []);
        });
    }

    // ===================
    // Клик
    // ===================

    function openOwnerCard(ownerType, ownerId) {
        const urls = {
            lead: `/crm/lead/details/${ownerId}/`,
            deal: `/crm/deal/details/${ownerId}/`,
            contact: `/crm/contact/details/${ownerId}/`,
            company: `/crm/company/details/${ownerId}/`
        };
        const url = urls[ownerType] || urls.deal;

        if (BX?.SidePanel?.Instance) {
            BX.SidePanel.Instance.open(url, { width: 1000 });
        } else {
            window.open(url, '_blank');
        }
    }

    function handleCrmActivityClick(entry, event) {
        if (!isCrmEntry(entry)) return false;
        log('CRM activity clicked:', entry.data?._activityId);
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        openOwnerCard(entry.data._ownerType, entry.data._ownerId);
        return true;
    }

    function findEntryByElement(element) {
        const cal = getCalendar();
        const view = cal?.getView();
        if (!view?.entries) return null;

        const entryWrap = element.closest('[data-bx-calendar-entry], .calendar-event-block-wrap, .calendar-grid-month-event-slot');
        if (!entryWrap) return null;

        const entryId = entryWrap.getAttribute('data-bx-calendar-entry') || entryWrap.dataset.entryId;

        if (entryId) {
            const entry = view.entries.find(e => String(e.id) === String(entryId) || e.uid === entryId);
            if (entry) return entry;
        }

        const titleEl = entryWrap.querySelector('.calendar-event-block-title, .calendar-item-content-name');
        if (titleEl) {
            const title = titleEl.textContent.trim();
            return view.entries.find(e => e.name === title && isCrmEntry(e));
        }

        return null;
    }

    // ===================
    // Блокировка Drag & Drop
    // ===================

    function patchDragAndDrop() {
        const cal = getCalendar();
        if (!cal) return;

        // Патчим eventDragAndDrop
        const edd = cal.dragDrop?.eventDragAndDrop;
        if (edd) {
            // Ищем все методы и патчим те, что связаны с началом drag
            const proto = Object.getPrototypeOf(edd);
            const methods = Object.getOwnPropertyNames(proto);
            
            log('eventDragAndDrop methods:', methods);
            
            // Патчим методы которые могут начинать drag
            ['onMouseDown', 'start', 'begin', 'init', 'startDrag'].forEach(name => {
                if (typeof edd[name] === 'function') {
                    const original = edd[name].bind(edd);
                    edd[name] = function(params) {
                        // Проверяем entry в параметрах или в this
                        const entry = params?.entry || this.entry || this.currentEntry;
                        if (isCrmEntry(entry)) {
                            log(`eventDragAndDrop.${name} blocked`);
                            return false;
                        }
                        return original(params);
                    };
                    log(`Patched eventDragAndDrop.${name}`);
                }
            });
        }

        // Патчим resizeDragAndDrop аналогично
        const rdd = cal.dragDrop?.resizeDragAndDrop;
        if (rdd) {
            ['onMouseDown', 'start', 'begin', 'init'].forEach(name => {
                if (typeof rdd[name] === 'function') {
                    const original = rdd[name].bind(rdd);
                    rdd[name] = function(params) {
                        const entry = params?.entry || this.entry || this.currentEntry;
                        if (isCrmEntry(entry)) {
                            log(`resizeDragAndDrop.${name} blocked`);
                            return false;
                        }
                        return original(params);
                    };
                    log(`Patched resizeDragAndDrop.${name}`);
                }
            });
        }

        log('Drag & Drop patching complete');
    }

    // Блокировка через DOM (резервный способ)
    function setupDomDragBlock() {
        const cal = getCalendar();
        if (!cal?.mainCont) return;

        let blockingEntry = null;

        cal.mainCont.addEventListener('mousedown', function(e) {
            const entry = findEntryByElement(e.target);
            if (isCrmEntry(entry)) {
                blockingEntry = entry;
                log('mousedown on CRM entry, blocking drag');
            }
        }, true);

        document.addEventListener('mousemove', function(e) {
            if (blockingEntry) {
                // Блокируем только если мышь двигается с зажатой кнопкой
                if (e.buttons === 1) {
                    e.stopPropagation();
                    e.preventDefault();
                }
            }
        }, true);

        document.addEventListener('mouseup', function() {
            if (blockingEntry) {
                log('mouseup, drag block released');
                blockingEntry = null;
            }
        }, true);

        log('DOM drag block setup complete');
    }

    // ===================
    // Инициализация
    // ===================

    function init() {
        log('Initializing...');

        if (typeof BX === 'undefined') {
            setTimeout(init, 500);
            return;
        }

        const cal = getCalendar();
        if (!cal) {
            BX.addCustomEvent('oncalendarafterbuildviews', function(calendar) {
                log('Calendar found via event');
                state.calendar = calendar;
                patchDragAndDrop();
                setupDomDragBlock();
                setupEventHandlers();
                update();
            });
            return;
        }

        patchDragAndDrop();
        setupDomDragBlock();
        setupEventHandlers();
        setTimeout(update, 500);
        state.initialized = true;
        log('Initialized');
    }

    function setupEventHandlers() {
        BX.addCustomEvent('changeviewrange', function() {
            state.currentDateFrom = null;
            state.currentDateTo = null;
            setTimeout(update, 300);
        });

        BX.addCustomEvent('aftersetview', function() {
            state.currentDateFrom = null;
            state.currentDateTo = null;
            setTimeout(update, 300);
        });

        BX.addCustomEvent('BX.Calendar:onEntryListReload', function() {
            setTimeout(update, 200);
        });

        const cal = getCalendar();
        
        // Клик на CRM активности
        if (cal?.views) {
            cal.views.forEach(view => {
                if (view.showCompactViewForm) {
                    const original = view.showCompactViewForm.bind(view);
                    view.showCompactViewForm = function(params) {
                        if (isCrmEntry(params?.entry)) {
                            handleCrmActivityClick(params.entry, null);
                            return;
                        }
                        return original(params);
                    };
                }
            });
        }

        if (cal?.mainCont) {
            cal.mainCont.addEventListener('click', function(e) {
                const entry = findEntryByElement(e.target);
                if (isCrmEntry(entry)) {
                    e.preventDefault();
                    e.stopPropagation();
                    handleCrmActivityClick(entry, e);
                }
            }, true);
        }

        log('Event handlers set up');
    }

    // Запуск
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 1000);
    }

    window.CRMCalendar = { update, refresh: refreshView, getState: () => ({ ...state }) };
    log('Script loaded');

})();