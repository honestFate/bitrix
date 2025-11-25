(function() {
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

        /**
         * Инициализация
         */
        init: function(config) {
            this.config = config;
            this.container = document.querySelector(config.containerId);
            
            if (!this.container) {
                console.error('CrmHlTab: Container not found');
                return;
            }

            this.bindEvents();
        },

        /**
         * Привязка событий
         */
        bindEvents: function() {
            var self = this;

            // Кнопка добавления
            var addBtn = this.container.querySelector('.crm-hl-tab-add-btn');
            if (addBtn) {
                BX.bind(addBtn, 'click', function() {
                    self.showAddForm();
                });
            }

            // Кнопки действий в таблице
            var editBtns = this.container.querySelectorAll('[data-action="edit"]');
            editBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function() {
                    var row = btn.closest('.crm-hl-tab-row');
                    self.enableEditMode(row);
                });
            });

            var saveBtns = this.container.querySelectorAll('[data-action="save"]');
            saveBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function() {
                    var row = btn.closest('.crm-hl-tab-row');
                    self.saveRow(row);
                });
            });

            var cancelBtns = this.container.querySelectorAll('[data-action="cancel"]');
            cancelBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function() {
                    var row = btn.closest('.crm-hl-tab-row');
                    self.disableEditMode(row);
                });
            });

            var deleteBtns = this.container.querySelectorAll('[data-action="delete"]');
            deleteBtns.forEach(function(btn) {
                BX.bind(btn, 'click', function() {
                    var row = btn.closest('.crm-hl-tab-row');
                    self.deleteRow(row);
                });
            });

            // Форма добавления
            var saveFormBtn = this.container.querySelector('.crm-hl-tab-form-save');
            if (saveFormBtn) {
                BX.bind(saveFormBtn, 'click', function() {
                    self.saveNewItem();
                });
            }

            var cancelFormBtn = this.container.querySelector('.crm-hl-tab-form-cancel');
            if (cancelFormBtn) {
                BX.bind(cancelFormBtn, 'click', function() {
                    self.hideAddForm();
                });
            }
        },

        /**
         * Показать форму добавления
         */
        showAddForm: function() {
            var form = this.container.querySelector('.crm-hl-tab-add-form');
            if (form) {
                form.style.display = 'block';
                
                // Скролл к форме
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        },

        /**
         * Скрыть форму добавления
         */
        hideAddForm: function() {
            var form = this.container.querySelector('.crm-hl-tab-add-form');
            if (form) {
                form.style.display = 'none';
                
                // Очистить поля
                var inputs = form.querySelectorAll('.crm-hl-tab-form-input');
                inputs.forEach(function(input) {
                    input.value = '';
                });
            }
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

            // Включить режим редактирования
            BX.addClass(row, 'editing');

            // Переключить кнопки
            this.toggleButtons(row, 'edit');
        },

        /**
         * Отключить режим редактирования строки
         */
        disableEditMode: function(row) {
            if (!row) return;

            BX.removeClass(row, 'editing');

            // Восстановить исходные значения
            var fields = row.querySelectorAll('.crm-hl-tab-field-edit input');
            fields.forEach(function(input) {
                var viewDiv = input.closest('.crm-hl-tab-td').querySelector('.crm-hl-tab-field-view');
                if (viewDiv) {
                    input.value = viewDiv.textContent.trim();
                }
            });

            // Переключить кнопки
            this.toggleButtons(row, 'view');
        },

        /**
         * Переключить кнопки
         */
        toggleButtons: function(row, mode) {
            var editBtn = row.querySelector('[data-action="edit"]');
            var saveBtn = row.querySelector('[data-action="save"]');
            var cancelBtn = row.querySelector('[data-action="cancel"]');
            var deleteBtn = row.querySelector('[data-action="delete"]');

            if (mode === 'edit') {
                if (editBtn) editBtn.style.display = 'none';
                if (saveBtn) saveBtn.style.display = 'inline-flex';
                if (cancelBtn) cancelBtn.style.display = 'inline-flex';
                if (deleteBtn) deleteBtn.style.display = 'none';
            } else {
                if (editBtn) editBtn.style.display = 'inline-flex';
                if (saveBtn) saveBtn.style.display = 'none';
                if (cancelBtn) cancelBtn.style.display = 'none';
                if (deleteBtn) deleteBtn.style.display = 'inline-flex';
            }
        },

        /**
         * Сохранить строку
         */
        saveRow: function(row) {
            if (!row) return;

            var itemId = row.dataset.itemId;
            var data = this.collectRowData(row);

            // Валидация
            if (!this.validateData(data)) {
                alert('Пожалуйста, заполните все обязательные поля');
                return;
            }

            // Добавить служебные данные
            data.hlBlockId = this.config.hlBlockId;
            data.itemId = itemId;
            data.companyId = this.config.companyId;
            data.action = 'update';

            this.sendAjaxRequest('save_data.php', data, function(response) {
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
            if (!this.validateData(data)) {
                alert('Пожалуйста, заполните все обязательные поля');
                return;
            }

            // Добавить служебные данные
            data.hlBlockId = this.config.hlBlockId;
            data.companyId = this.config.companyId;
            data.action = 'add';

            this.sendAjaxRequest('save_data.php', data, function(response) {
                if (response.success) {
                    this.showNotification('Элемент успешно добавлен', 'success');
                    this.hideAddForm();
                    
                    // Перезагрузить страницу для обновления данных
                    setTimeout(function() {
                        location.reload();
                    }, 500);
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

            if (!confirm('Вы уверены, что хотите удалить этот элемент?')) {
                return;
            }

            var itemId = row.dataset.itemId;
            var data = {
                hlBlockId: this.config.hlBlockId,
                itemId: itemId,
                action: 'delete'
            };

            this.sendAjaxRequest('save_data.php', data, function(response) {
                if (response.success) {
                    // Удалить строку из DOM с анимацией
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    
                    setTimeout(function() {
                        row.remove();
                        
                        // Проверить, есть ли еще строки
                        var rows = this.container.querySelectorAll('.crm-hl-tab-row');
                        if (rows.length === 0) {
                            location.reload();
                        }
                    }.bind(this), 300);
                    
                    this.showNotification('Элемент успешно удален', 'success');
                } else {
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
        validateData: function(data) {
            // Простая проверка на пустые обязательные поля
            for (var key in data) {
                if (data[key] === '' && document.querySelector('[data-field-code="' + key + '"][required]')) {
                    return false;
                }
            }
            return true;
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
                        viewDiv.textContent = data[fieldCode];
                    }
                }
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
                onfailure: function() {
                    this.showNotification('Ошибка при выполнении запроса', 'error');
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
                    autoHideDelay: 3000
                });
            } else {
                alert(message);
            }
        }
    };
})();