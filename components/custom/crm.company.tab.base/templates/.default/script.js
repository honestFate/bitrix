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
                console.error('CrmHlTab: Container not found -', config.containerId);
                return;
            }

            console.log('CrmHlTab: Initialized for', config.tabCode);
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
            if (!form) return;

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
                    this.showNotification('Элемент успешно добавлен', 'success');
                    this.hideAddForm();
                    
                    // Перезагрузить страницу для обновления данных
                    setTimeout(function() {
                        location.reload();
                    }, 800);
                } else {
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

            // Показать загрузку
            BX.addClass(row, 'crm-hl-tab-loading');

            this.sendAjaxRequest('save_data.php', data, function(response) {
                if (response.success) {
                    // Анимация удаления
                    row.style.transition = 'opacity 0.3s, transform 0.3s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    
                    setTimeout(function() {
                        row.remove();
                        
                        // Проверить, есть ли еще строки
                        var rows = this.container.querySelectorAll('.crm-hl-tab-row');
                        if (rows.length === 0) {
                            setTimeout(function() {
                                location.reload();
                            }, 300);
                        } else {
                            // Обновить счетчик
                            this.updateCount();
                        }
                    }.bind(this), 300);
                    
                    this.showNotification('Элемент успешно удален', 'success');
                } else {
                    BX.removeClass(row, 'crm-hl-tab-loading');
                    this.showNotification(response.error || 'Ошибка при удалении', 'error');
                }
            }.bind(this));
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
            
            // Получить все обязательные поля
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
            BX.ajax({
                url: this.config.ajaxPath + file,
                data: data,
                method: 'POST',
                dataType: 'json',
                onsuccess: callback,
                onfailure: function(error) {
                    console.error('AJAX Error:', error);
                    this.showNotification('Ошибка при выполнении запроса. Проверьте соединение.', 'error');
                }.bind(this)
            });
        },

        /**
         * Показать уведомление
         */
        showNotification: function(message, type) {
            // Попытка использовать стандартные уведомления Bitrix
            if (typeof BX.UI !== 'undefined' && BX.UI.Notification) {
                BX.UI.Notification.Center.notify({
                    content: message,
                    position: 'top-right',
                    autoHideDelay: type === 'error' ? 5000 : 3000
                });
            } else {
                // Fallback на alert
                alert(message);
            }
        }
    };

})(window);