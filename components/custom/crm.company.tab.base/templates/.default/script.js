(function(window) {
    'use strict';

    if (typeof BX.CrmHlTab !== 'undefined') {
        return;
    }

    /**
     * Класс для работы с вкладками Highload-блоков в CRM
     */
    BX.CrmHlTab = {
        config: {},
        container: null,
        formVisible: false,

        /**
         * Инициализация
         */
        init: function(config) {
            this.config = config;
            this.container = document.querySelector(config.containerId);
            
            if (!this.container) {
                console.error('[CrmHlTab] Container not found:', config.containerId);
                return;
            }

            console.log('[CrmHlTab] Initialized for', config.tabCode);
            this.bindEvents();
        },

        /**
         * Привязка событий
         */
        bindEvents: function() {
            var self = this;

            // Кнопки добавления
            var addBtns = this.container.querySelectorAll('[data-action="add"]');
            addBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function(e) {
                    e.preventDefault();
                    self.showAddForm();
                });
            });

            // Кнопка закрытия формы
            var closeBtn = this.container.querySelector('[data-action="close-form"]');
            if (closeBtn) {
                BX.bind(closeBtn, 'click', function(e) {
                    e.preventDefault();
                    self.hideAddForm();
                });
            }

            // Кнопки редактирования в таблице
            var editBtns = this.container.querySelectorAll('[data-action="edit"]');
            editBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function(e) {
                    e.preventDefault();
                    var row = btn.closest('.crm-hl-tab-row');
                    self.enableEditMode(row);
                });
            });

            // Кнопки сохранения в таблице
            var saveBtns = this.container.querySelectorAll('[data-action="save"]');
            saveBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function(e) {
                    e.preventDefault();
                    var row = btn.closest('.crm-hl-tab-row');
                    self.saveRow(row);
                });
            });

            // Кнопки отмены в таблице
            var cancelBtns = this.container.querySelectorAll('[data-action="cancel"]');
            cancelBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function(e) {
                    e.preventDefault();
                    var row = btn.closest('.crm-hl-tab-row');
                    self.disableEditMode(row);
                });
            });

            // Кнопки удаления
            var deleteBtns = this.container.querySelectorAll('[data-action="delete"]');
            deleteBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function(e) {
                    e.preventDefault();
                    var row = btn.closest('.crm-hl-tab-row');
                    self.deleteRow(row);
                });
            });

            // Форма добавления - сохранить
            var saveFormBtn = this.container.querySelector('.crm-hl-tab-form-save');
            if (saveFormBtn) {
                BX.bind(saveFormBtn, 'click', function(e) {
                    e.preventDefault();
                    self.saveNewItem();
                });
            }

            // Форма добавления - отменить
            var cancelFormBtn = this.container.querySelector('.crm-hl-tab-form-cancel');
            if (cancelFormBtn) {
                BX.bind(cancelFormBtn, 'click', function(e) {
                    e.preventDefault();
                    self.hideAddForm();
                });
            }

            // Enter в полях формы - сохранить
            var formInputs = this.container.querySelectorAll('.crm-hl-tab-form-input');
            formInputs.forEach(function(input) {
                BX.bind(input, 'keypress', function(e) {
                    if (e.key === 'Enter' || e.keyCode === 13) {
                        e.preventDefault();
                        self.saveNewItem();
                    }
                });
            });

            // Escape - закрыть форму или отменить редактирование
            BX.bind(document, 'keydown', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    var editingRow = self.container.querySelector('.crm-hl-tab-row.editing');
                    if (editingRow) {
                        self.disableEditMode(editingRow);
                    } else if (self.formVisible) {
                        self.hideAddForm();
                    }
                }
            });
        },

        /**
         * Показать форму добавления
         */
        showAddForm: function() {
            var form = this.container.querySelector('.crm-hl-tab-add-form');
            if (!form) {
                console.error('[CrmHlTab] Add form not found');
                return;
            }

            // Скрыть режим редактирования строк
            var editingRows = this.container.querySelectorAll('.crm-hl-tab-row.editing');
            editingRows.forEach(function(row) {
                this.disableEditMode(row);
            }.bind(this));

            form.style.display = 'block';
            BX.addClass(form, 'active');
            this.formVisible = true;
            
            // Фокус на первое поле
            var firstInput = form.querySelector('.crm-hl-tab-form-input');
            if (firstInput) {
                setTimeout(function() {
                    firstInput.focus();
                }, 100);
            }

            // Плавный скролл к форме
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },

        /**
         * Скрыть форму добавления
         */
        hideAddForm: function() {
            var form = this.container.querySelector('.crm-hl-tab-add-form');
            if (!form) return;

            BX.removeClass(form, 'active');
            
            setTimeout(function() {
                form.style.display = 'none';
                this.formVisible = false;
            }.bind(this), 300);
            
            // Очистить поля
            var inputs = form.querySelectorAll('.crm-hl-tab-form-input');
            inputs.forEach(function(input) {
                input.value = '';
                input.classList.remove('error');
            });
        },

        /**
         * Включить режим редактирования строки
         */
        enableEditMode: function(row) {
            if (!row) return;

            // Отключить редактирование других строк
            var otherRows = this.container.querySelectorAll('.crm-hl-tab-row.editing');
            otherRows.forEach(function(otherRow) {
                if (otherRow !== row) {
                    this.disableEditMode(otherRow);
                }
            }.bind(this));

            // Скрыть форму добавления
            if (this.formVisible) {
                this.hideAddForm();
            }

            // Включить режим редактирования
            BX.addClass(row, 'editing');

            // Фокус на первое поле
            var firstInput = row.querySelector('.crm-hl-tab-field-edit input');
            if (firstInput) {
                setTimeout(function() {
                    firstInput.focus();
                    firstInput.select();
                }, 100);
            }
        },

        /**
         * Отключить режим редактирования строки
         */
        disableEditMode: function(row) {
            if (!row) return;

            // Восстановить исходные значения
            var fields = row.querySelectorAll('.crm-hl-tab-field-edit input');
            fields.forEach(function(input) {
                var viewDiv = input.closest('.crm-hl-tab-td').querySelector('.crm-hl-tab-field-view');
                if (viewDiv) {
                    var originalValue = viewDiv.textContent.trim();
                    if (originalValue === '—') {
                        originalValue = '';
                    }
                    input.value = originalValue;
                }
                input.classList.remove('error');
            });

            BX.removeClass(row, 'editing');
        },

        /**
         * Сохранить строку
         */
        saveRow: function(row) {
            if (!row) return;

            var itemId = row.dataset.itemId;
            var data = this.collectRowData(row);

            // Валидация
            var validationErrors = this.validateData(data, row);
            if (validationErrors.length > 0) {
                this.showValidationErrors(validationErrors);
                return;
            }

            console.log('[CrmHlTab] Saving row', itemId, data);

            // Показать загрузку
            BX.addClass(row, 'crm-hl-tab-loading');

            // Добавить служебные данные
            data.hlBlockId = this.config.hlBlockId;
            data.itemId = itemId;
            data.companyId = this.config.companyId;
            data.action = 'update';

            this.sendAjaxRequest('save_data.php', data, function(response) {
                BX.removeClass(row, 'crm-hl-tab-loading');
                
                if (response.success) {
                    this.updateRowView(row, data);
                    this.disableEditMode(row);
                    this.showNotification('Данные успешно сохранены', 'success');
                } else {
                    console.error('[CrmHlTab] Save error:', response.error);
                    this.showNotification(response.error || 'Ошибка при сохранении', 'error');
                }
            }.bind(this));
        },

        /**
         * Сохранить новый элемент
         */
        saveNewItem: function() {
            var form = this.container.querySelector('.crm-hl-tab-add-form');
            if (!form) return;

            var data = this.collectFormData(form);

            // Валидация
            var validationErrors = this.validateData(data, form);
            if (validationErrors.length > 0) {
                this.showValidationErrors(validationErrors);
                return;
            }

            console.log('[CrmHlTab] Saving new item', data);

            // Показать загрузку
            var saveBtn = form.querySelector('.crm-hl-tab-form-save');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Сохранение...';
            }

            // Добавить служебные данные
            data.hlBlockId = this.config.hlBlockId;
            data.companyId = this.config.companyId;
            data.action = 'add';

            this.sendAjaxRequest('save_data.php', data, function(response) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;"><path d="M13.3333 4L6 11.3333L2.66667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Сохранить';
                }

                if (response.success) {
                    console.log('[CrmHlTab] Item added successfully', response);
                    this.showNotification('Элемент успешно добавлен', 'success');
                    this.hideAddForm();
                    
                    // ИСПРАВЛЕНИЕ: ID находится в response.data.id
                    var newId = response.data ? response.data.id : response.id;
                    this.addRowToTable(newId, data);
                } else {
                    console.error('[CrmHlTab] Add error:', response.error);
                    this.showNotification(response.error || 'Ошибка при добавлении', 'error');
                }
            }.bind(this));
        },

        /**
         * Удалить строку
         */
        deleteRow: function(row) {
            if (!row) return;

            if (!confirm('Вы уверены, что хотите удалить этот элемент? Это действие нельзя отменить.')) {
                return;
            }

            var itemId = row.dataset.itemId;
            var data = {
                hlBlockId: this.config.hlBlockId,
                itemId: itemId,
                action: 'delete'
            };

            console.log('[CrmHlTab] Deleting row', itemId);

            BX.addClass(row, 'crm-hl-tab-loading');

            this.sendAjaxRequest('save_data.php', data, function(response) {
                if (response.success) {
                    row.style.transition = 'opacity 0.3s, transform 0.3s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    
                    setTimeout(function() {
                        row.remove();
                        
                        var tbody = this.container.querySelector('.crm-hl-tab-table tbody');
                        var rows = tbody ? tbody.querySelectorAll('.crm-hl-tab-row') : [];
                        
                        if (rows.length === 0) {
                            this.showEmptyState();
                        }
                        
                        this.updateCount();
                    }.bind(this), 300);
                    
                    this.showNotification('Элемент успешно удален', 'success');
                } else {
                    BX.removeClass(row, 'crm-hl-tab-loading');
                    console.error('[CrmHlTab] Delete error:', response.error);
                    this.showNotification(response.error || 'Ошибка при удалении', 'error');
                }
            }.bind(this));
        },

        /**
         * Создать структуру таблицы
         */
        createTableStructure: function() {
            var content = this.container.querySelector('.crm-hl-tab-content');
            if (!content) return null;
            
            var emptyState = content.querySelector('.crm-hl-tab-empty');
            if (emptyState) {
                emptyState.style.display = 'none';
            }
            
            var existingWrapper = content.querySelector('.crm-hl-tab-table-wrapper');
            if (existingWrapper) {
                existingWrapper.style.display = 'block';
                return existingWrapper.querySelector('.crm-hl-tab-table tbody');
            }
            
            var tableWrapper = document.createElement('div');
            tableWrapper.className = 'crm-hl-tab-table-wrapper';
            
            var table = document.createElement('table');
            table.className = 'crm-hl-tab-table';
            
            var thead = document.createElement('thead');
            var headerRow = document.createElement('tr');
            
            // ID колонка
            var thId = document.createElement('th');
            thId.className = 'crm-hl-tab-th-id';
            thId.innerHTML = '<div class="crm-hl-tab-th-content">ID</div>';
            headerRow.appendChild(thId);
            
            // Поля из формы добавления
            var formFields = this.container.querySelectorAll('.crm-hl-tab-form-field');
            formFields.forEach(function(field) {
                var label = field.querySelector('.crm-hl-tab-form-label');
                var input = field.querySelector('.crm-hl-tab-form-input');
                
                if (label && input) {
                    var th = document.createElement('th');
                    th.className = 'crm-hl-tab-th';
                    
                    var thContent = document.createElement('div');
                    thContent.className = 'crm-hl-tab-th-content';
                    thContent.textContent = label.textContent.replace('*', '').trim();
                    
                    if (input.hasAttribute('required')) {
                        var required = document.createElement('span');
                        required.className = 'crm-hl-tab-required';
                        required.textContent = '*';
                        thContent.appendChild(required);
                    }
                    
                    th.appendChild(thContent);
                    headerRow.appendChild(th);
                }
            });
            
            if (this.config.permissions.CAN_EDIT || this.config.permissions.CAN_DELETE) {
                var thActions = document.createElement('th');
                thActions.className = 'crm-hl-tab-th-actions';
                thActions.innerHTML = '<div class="crm-hl-tab-th-content">Действия</div>';
                headerRow.appendChild(thActions);
            }
            
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            var tbody = document.createElement('tbody');
            table.appendChild(tbody);
            
            tableWrapper.appendChild(table);
            
            var addForm = content.querySelector('.crm-hl-tab-add-form');
            if (addForm) {
                content.insertBefore(tableWrapper, addForm);
            } else {
                content.appendChild(tableWrapper);
            }
            
            return tbody;
        },

        /**
         * Добавить новую строку в таблицу
         */
        addRowToTable: function(itemId, data) {
            var tableWrapper = this.container.querySelector('.crm-hl-tab-table-wrapper');
            var tbody = this.container.querySelector('.crm-hl-tab-table tbody');
            var emptyState = this.container.querySelector('.crm-hl-tab-empty');
            
            if (!tbody) {
                tbody = this.createTableStructure();
                if (!tbody) {
                    console.error('[CrmHlTab] Failed to create table structure');
                    return;
                }
            }
            
            if (emptyState) {
                emptyState.style.display = 'none';
            }
            
            if (tableWrapper) {
                tableWrapper.style.display = 'block';
            }
            
            var newRow = this.createTableRow(itemId, data);
            if (newRow) {
                tbody.appendChild(newRow);
                this.bindRowEvents(newRow);
                
                newRow.style.opacity = '0';
                setTimeout(function() {
                    newRow.style.transition = 'opacity 0.3s';
                    newRow.style.opacity = '1';
                }, 10);
                
                this.updateCount();
            }
        },

        /**
         * Создание HTML таблицы
         */
        createTableRow: function(itemId, data) {
            var row = document.createElement('tr');
            row.className = 'crm-hl-tab-row crm-hl-tab-fade-in';
            row.dataset.itemId = itemId;
            
            // ID колонка
            var idTd = document.createElement('td');
            idTd.className = 'crm-hl-tab-td-id';
            idTd.innerHTML = '<span class="crm-hl-tab-id-badge">' + itemId + '</span>';
            row.appendChild(idTd);
            
            // Поля данных
            for (var fieldCode in data) {
                if (fieldCode.indexOf('UF_') === 0 && fieldCode !== 'UF_COMPANY_ID') {
                    var td = document.createElement('td');
                    td.className = 'crm-hl-tab-td';
                    td.dataset.field = fieldCode;
                    
                    var value = data[fieldCode] || '';
                    var displayValue = value || '<span class="crm-hl-tab-empty-value">—</span>';
                    
                    td.innerHTML = '<div class="crm-hl-tab-field-view">' + displayValue + '</div>';
                    
                    // Поле редактирования
                    if (this.config.permissions.CAN_EDIT) {
                        var editDiv = document.createElement('div');
                        editDiv.className = 'crm-hl-tab-field-edit';
                        editDiv.innerHTML = '<input type="text" class="crm-hl-tab-input" value="' + 
                                          (value || '').replace(/"/g, '&quot;') + '" data-field-code="' + fieldCode + '">';
                        td.appendChild(editDiv);
                    }
                    
                    row.appendChild(td);
                }
            }
            
            // Колонка действий
            if (this.config.permissions.CAN_EDIT || this.config.permissions.CAN_DELETE) {
                var actionsTd = document.createElement('td');
                actionsTd.className = 'crm-hl-tab-td-actions';
                
                var actionsDiv = document.createElement('div');
                actionsDiv.className = 'crm-hl-tab-actions';
                
                if (this.config.permissions.CAN_EDIT) {
                    actionsDiv.innerHTML += 
                        '<button class="crm-hl-tab-btn crm-hl-tab-btn-edit" data-action="edit" title="Редактировать">' +
                            '<svg width="16" height="16" viewBox="0 0 16 16" fill="none">' +
                                '<path d="M11.333 2.00004C11.5081 1.82494 11.716 1.68605 11.9447 1.59129C12.1735 1.49653 12.4187 1.44775 12.6663 1.44775C12.914 1.44775 13.1592 1.49653 13.3879 1.59129C13.6167 1.68605 13.8246 1.82494 13.9997 2.00004C14.1748 2.17513 14.3137 2.383 14.4084 2.61178C14.5032 2.84055 14.552 3.08575 14.552 3.33337C14.552 3.58099 14.5032 3.82619 14.4084 4.05497C14.3137 4.28374 14.1748 4.49161 13.9997 4.66671L5.33301 13.3334L1.99967 14.3334L2.99967 11L11.333 2.00004Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                            '</svg>' +
                        '</button>' +
                        '<button class="crm-hl-tab-btn crm-hl-tab-btn-save" data-action="save" title="Сохранить">' +
                            '<svg width="16" height="16" viewBox="0 0 16 16" fill="none">' +
                                '<path d="M13.3333 4L6 11.3333L2.66667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
                            '</svg>' +
                        '</button>' +
                        '<button class="crm-hl-tab-btn crm-hl-tab-btn-cancel" data-action="cancel" title="Отменить">' +
                            '<svg width="16" height="16" viewBox="0 0 16 16" fill="none">' +
                                '<path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
                            '</svg>' +
                        '</button>';
                }
                
                if (this.config.permissions.CAN_DELETE) {
                    actionsDiv.innerHTML += 
                        '<button class="crm-hl-tab-btn crm-hl-tab-btn-delete" data-action="delete" title="Удалить">' +
                            '<svg width="16" height="16" viewBox="0 0 16 16" fill="none">' +
                                '<path d="M2 4H3.33333H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                                '<path d="M5.33301 4.00004V2.66671C5.33301 2.31309 5.47348 1.97395 5.72353 1.7239C5.97358 1.47385 6.31272 1.33337 6.66634 1.33337H9.33301C9.68663 1.33337 10.0258 1.47385 10.2758 1.7239C10.5259 1.97395 10.6663 2.31309 10.6663 2.66671V4.00004M12.6663 4.00004V13.3334C12.6663 13.687 12.5259 14.0261 12.2758 14.2762C12.0258 14.5262 11.6866 14.6667 11.333 14.6667H4.66634C4.31272 14.6667 3.97358 14.5262 3.72353 14.2762C3.47348 14.0261 3.33301 13.687 3.33301 13.3334V4.00004H12.6663Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                            '</svg>' +
                        '</button>';
                }
                
                actionsTd.appendChild(actionsDiv);
                row.appendChild(actionsTd);
            }
            
            return row;
        },

        /**
         * Привязать события к строке
         */
        bindRowEvents: function(row) {
            var self = this;
            
            var editBtn = row.querySelector('[data-action="edit"]');
            if (editBtn) {
                BX.bind(editBtn, 'click', function(e) {
                    e.preventDefault();
                    self.enableEditMode(row);
                });
            }
            
            var saveBtn = row.querySelector('[data-action="save"]');
            if (saveBtn) {
                BX.bind(saveBtn, 'click', function(e) {
                    e.preventDefault();
                    self.saveRow(row);
                });
            }
            
            var cancelBtn = row.querySelector('[data-action="cancel"]');
            if (cancelBtn) {
                BX.bind(cancelBtn, 'click', function(e) {
                    e.preventDefault();
                    self.disableEditMode(row);
                });
            }
            
            var deleteBtn = row.querySelector('[data-action="delete"]');
            if (deleteBtn) {
                BX.bind(deleteBtn, 'click', function(e) {
                    e.preventDefault();
                    self.deleteRow(row);
                });
            }
        },

        /**
         * Показать пустое состояние
         */
        showEmptyState: function() {
            var content = this.container.querySelector('.crm-hl-tab-content');
            if (!content) return;
            
            var tableWrapper = content.querySelector('.crm-hl-tab-table-wrapper');
            if (tableWrapper) {
                tableWrapper.style.display = 'none';
            }
            
            var emptyState = content.querySelector('.crm-hl-tab-empty');
            if (!emptyState) {
                emptyState = document.createElement('div');
                emptyState.className = 'crm-hl-tab-empty';
                emptyState.innerHTML = 
                    '<div class="crm-hl-tab-empty-icon">📋</div>' +
                    '<div class="crm-hl-tab-empty-text">Нет данных для отображения</div>';
                
                if (this.config.permissions.CAN_ADD) {
                    emptyState.innerHTML += 
                        '<button class="ui-btn ui-btn-primary crm-hl-tab-add-btn-empty" data-action="add">' +
                            'Добавить первый элемент' +
                        '</button>';
                }
                
                var addForm = content.querySelector('.crm-hl-tab-add-form');
                if (addForm) {
                    content.insertBefore(emptyState, addForm);
                } else {
                    content.appendChild(emptyState);
                }
                
                // Привязать событие к кнопке
                var addBtn = emptyState.querySelector('[data-action="add"]');
                if (addBtn) {
                    BX.bind(addBtn, 'click', function(e) {
                        e.preventDefault();
                        this.showAddForm();
                    }.bind(this));
                }
            }
            
            emptyState.style.display = 'flex';
        },

        /**
         * Собрать данные из строки
         */
        collectRowData: function(row) {
            var data = {};
            var inputs = row.querySelectorAll('.crm-hl-tab-field-edit input');
            
            inputs.forEach(function(input) {
                var fieldCode = input.dataset.fieldCode;
                data[fieldCode] = input.value.trim();
            });

            return data;
        },

        /**
         * Собрать данные из формы
         */
        collectFormData: function(form) {
            var data = {};
            var inputs = form.querySelectorAll('.crm-hl-tab-form-input');
            
            inputs.forEach(function(input) {
                var fieldCode = input.dataset.fieldCode;
                data[fieldCode] = input.value.trim();
            });

            return data;
        },

        /**
         * Валидация данных
         */
        validateData: function(data, container) {
            var errors = [];

            var requiredInputs = container.querySelectorAll('input[required]');
            
            requiredInputs.forEach(function(input) {
                var fieldCode = input.dataset.fieldCode;
                var fieldName = input.placeholder || input.closest('.crm-hl-tab-form-field')?.querySelector('label')?.textContent || fieldCode;
                
                input.classList.remove('error');
                
                if (!data[fieldCode] || data[fieldCode] === '') {
                    errors.push('Поле "' + fieldName + '" обязательно для заполнения');
                    input.classList.add('error');
                }
            });

            return errors;
        },

        /**
         * Показать ошибки валидации
         */
        showValidationErrors: function(errors) {
            var message = errors.join('\n');
            alert(message);
        },

        /**
         * Обновить отображение строки
         */
        updateRowView: function(row, data) {
            for (var fieldCode in data) {
                var td = row.querySelector('[data-field="' + fieldCode + '"]');
                if (td) {
                    var viewDiv = td.querySelector('.crm-hl-tab-field-view');
                    if (viewDiv) {
                        if (data[fieldCode]) {
                            viewDiv.textContent = data[fieldCode];
                            viewDiv.classList.remove('crm-hl-tab-empty-value');
                        } else {
                            viewDiv.innerHTML = '<span class="crm-hl-tab-empty-value">—</span>';
                        }
                    }
                }
            }
        },

        /**
         * Обновить счетчик записей
         */
        updateCount: function() {
            var countEl = this.container.querySelector('.crm-hl-tab-count-number');
            if (countEl) {
                var rows = this.container.querySelectorAll('.crm-hl-tab-row');
                countEl.textContent = rows.length;
            }
        },

        /**
         * Отправить AJAX-запрос
         */
        sendAjaxRequest: function(file, data, callback) {
            console.log('[CrmHlTab] Sending AJAX request to', this.config.ajaxPath + file, data);
            
            BX.ajax({
                url: this.config.ajaxPath + file,
                data: data,
                method: 'POST',
                dataType: 'json',
                onsuccess: function(response) {
                    console.log('[CrmHlTab] AJAX response:', response);
                    callback(response);
                },
                onfailure: function(error) {
                    console.error('[CrmHlTab] AJAX Error:', error);
                    this.showNotification('Ошибка при выполнении запроса. Проверьте соединение.', 'error');
                }.bind(this)
            });
        },

        /**
         * Показать уведомление
         */
        showNotification: function(message, type) {
            if (typeof BX.UI !== 'undefined' && BX.UI.Notification) {
                BX.UI.Notification.Center.notify({
                    content: message,
                    position: 'top-right',
                    autoHideDelay: type === 'error' ? 5000 : 3000
                });
            } else {
                alert(message);
            }
        }
    };

})(window);