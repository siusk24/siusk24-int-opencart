var S24_INT_SETTINGS = {

    parcelDefaultModal: '#parcel_default_modal',
    shippingMethodModal: '#shipping_option_modal',
    globalPriceTypeAddon: '#shipping_option_modal [data-global-price-addon]',

    cloneList: {
        apiCountriesSelect: 'apiCountriesSelectEl',
        noCountryPlaceholder: 'noCountryPlaceholderEl'
    },

    noCountryPlaceholderEl: null,
    apiCountriesSelectEl: null,

    init: function () {
        this.cloneDefaultElements();
        this.listenForPaginator();
        this.listenForClicks();
        this.registerGlobalEvents();
        this.renderShippingServices()

        // sender tab countries select
        jQuery('#tab-sender-info .js-select-sender').select2({
            width: 'resolve'
        });

        S24_INT_M_COMMON.showLoadingOverlay(false, document.querySelector('#content'));
    },

    cloneDefaultElements: function () {
        this.noCountryPlaceholderEl = document.querySelector('[data-modal-no-country]').cloneNode(true);
        this.apiCountriesSelectEl = document.querySelector('.sm-modal-api-countries select[name="api_countries"]').cloneNode(true);
    },

    getCloneEl: function (key) {
        if (S24_INT_SETTINGS[key] instanceof Node) {
            return S24_INT_SETTINGS[key].cloneNode(true)
        }

        return null;
    },

    registerGlobalEvents: function () {
        // Shipping method section expand
        S24_INT_M_COMMON.addGlobalListener('click', '[data-expand-section]', (e) => {
            e.preventDefault();
            const section = e.target.closest('section');
            section.dataset.expanded = section.dataset.expanded == 1 ? 0 : 1;
        });

        // Shipping method add/edit
        S24_INT_M_COMMON.addGlobalListener('click', '[data-btn-add-option]', S24_INT_SETTINGS.openShippingMethodModal);

        // Shipping method add
        S24_INT_M_COMMON.addGlobalListener('click', '[data-btn-delete-option]', (e) => {
            e.preventDefault();
            S24_INT_M_COMMON.confirm({
                message: `Delete shipping option ID: ${e.target.dataset.shippingMethodId}?`,
                accept: () => {
                    console.log('Delete shipping method ID:', e.target.dataset.shippingMethodId);
                    S24_INT_SETTINGS.deleteShippingOption(e.target.dataset.shippingMethodId);
                },
                cancel: () => { }
            });
        });

        // Shipping method save
        S24_INT_M_COMMON.addGlobalListener('click', '[data-btn-save-add-shipping-method]', (e) => {
            e.preventDefault();

            const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
            // check if save button for country price line exist, if so means there is unsaved lines
            if (modal.querySelector('[data-cell-btn-save]')) {
                S24_INT_M_COMMON.confirm({
                    message: S24_INT_M_SETTINGS_DATA.strings.price_edit_unsaved_alert,
                    accept: () => {
                        S24_INT_SETTINGS.saveShippingOption();
                    }
                });

                return;
            }

            S24_INT_SETTINGS.saveShippingOption();
        });

        // Shipping medthod discard
        S24_INT_M_COMMON.addGlobalListener('click', '[data-btn-cancel-add-shipping-method]', (e) => {
            e.preventDefault();

            const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
            // check if save button for country price line exist, if so means there is unsaved lines
            if (modal.querySelector('[data-cell-btn-save]')) {
                S24_INT_M_COMMON.confirm({
                    message: S24_INT_M_SETTINGS_DATA.strings.price_edit_unsaved_alert,
                    accept: () => {
                        modal.classList.add('hidden');
                    }
                });

                return;
            }

            modal.classList.add('hidden');
        });

        // Shipping method modal type change
        S24_INT_M_COMMON.addGlobalListener('change', 'input[name="option_type"]', (e) => {
            e.preventDefault();

            S24_INT_SETTINGS.shippingMethodModalSelectType(e.target.value);
        });

        // Shipping method modal sevices select buttons
        S24_INT_M_COMMON.addGlobalListener('click', '[data-select-service-btn]', S24_INT_SETTINGS.shippingMethodModalServicesSelect);

        // Shipping method modal, price type change
        S24_INT_M_COMMON.addGlobalListener('change', '[data-price-type-selector-addon]', (e) => {
            e.preventDefault();

            S24_INT_SETTINGS.changePriceTypeAddon(e.target.dataset.priceTypeSelectorAddon, e.target.value);
        });

        // Shipping method modal, add country button
        S24_INT_M_COMMON.addGlobalListener('click', '.sm-modal-add-country-btn', (e) => {
            e.preventDefault();
            const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
            const countrySelector = modal.querySelector('select[name="api_countries"]');

            S24_INT_SETTINGS.saveOptionCountry({
                option_id: modal.dataset.shippingOptionId,
                country_code: countrySelector.value,
                offer_priority: null,
                price_type: null,
                price: null,
                free_shipping: null
            }, modal.querySelector('.panel'), (response) => {
                S24_INT_SETTINGS.shippingMethodModalAddCountryRow(response.fields);
            });
        });

        // Shipping method modal, edit country button
        S24_INT_M_COMMON.addGlobalListener('click', '[data-cell-btn-edit]', (e) => {
            e.preventDefault();
            console.log('Edit Country row');
            S24_INT_SETTINGS.shippingMethodModalCountryRowAction(e.target.dataset.cellBtnEdit, true);
        });
        // Shipping method modal, delete country button
        S24_INT_M_COMMON.addGlobalListener('click', '[data-cell-btn-delete]', (e) => {
            e.preventDefault();

            S24_INT_M_COMMON.confirm({
                message: `${S24_INT_M_SETTINGS_DATA.strings.price_country_delete_alert} ${e.target.dataset.cellBtnDelete}?`,
                accept: () => {
                    console.log('Delete Country');
                    const countryCode = e.target.dataset.cellBtnDelete;
                    const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);

                    S24_INT_SETTINGS.deleteOptionCountry(modal.dataset.shippingOptionId, countryCode, () => {
                        const countrySelector = modal.querySelector('select[name="api_countries"]');
                        const country = S24_INT_SETTINGS.findCountryData(countryCode);
                        const countryTable = modal.querySelector('#sm-modal-countries');
                        const countryRow = countryTable.querySelector(`tr[data-country-code="${countryCode}"]`);

                        if (countryRow) {
                            countryRow.remove();
                        }

                        if (country && !countrySelector.querySelector(`option[value="${countryCode}"]`)) {
                            const option = document.createElement('option');
                            option.innerText = country.en_name;
                            option.value = country.code;
                            countrySelector.append(option);
                        }

                        if (countryTable.children.length < 1 && S24_INT_SETTINGS.noCountryPlaceholderEl) {
                            countryTable.append(S24_INT_SETTINGS.getCloneEl(S24_INT_SETTINGS.cloneList.noCountryPlaceholder));
                        }
                    });
                },
                cancel: () => { }
            });
        });
        // Shipping method modal, save edit country button
        S24_INT_M_COMMON.addGlobalListener('click', '[data-cell-btn-save]', (e) => {
            e.preventDefault();
            console.log('Saving row data');
            const countryCode = e.target.dataset.cellBtnSave;
            const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
            const countryTable = modal.querySelector('#sm-modal-countries');
            const row = countryTable.querySelector(`tr[data-country-code="${countryCode}"]`);

            const data = {
                option_id: modal.dataset.shippingOptionId,
                country_code: countryCode,
                offer_priority: row.querySelector('[data-cell-priority] select').value,
                price_type: row.querySelector('[data-cell-price-type] select').value,
                price: row.querySelector('[data-cell-price] input').value,
                free_shipping: row.querySelector('[data-cell-free-shipping] input').value,
            };

            console.log('Data to save', data);
            S24_INT_SETTINGS.saveOptionCountry(data, row, (response) => {
                S24_INT_SETTINGS.shippingMethodModalCountryUpdateRowData(response.fields);
            });
        });
        // Shipping method modal, cancel edit country button
        S24_INT_M_COMMON.addGlobalListener('click', '[data-cell-btn-cancel]', (e) => {
            e.preventDefault();
            console.log('Canceled row edit');
            S24_INT_SETTINGS.shippingMethodModalCountryRowAction(e.target.dataset.cellBtnCancel, false);
        });
    },

    listenForClicks: function () {
        document.addEventListener('click', function (e) {
            if (e.target.matches('.save-global-dimensions')) {
                e.preventDefault();
                S24_INT_SETTINGS.handleGlobalPdUpdate(e);
                return;
            }

            if (e.target.matches('.edit-pd-category')) {
                e.preventDefault();
                console.log(e.target.dataset);
                S24_INT_SETTINGS.fillPdModal(e.target.dataset);
                return;
            }

            if (e.target.matches('.reset-pd-category')) {
                e.preventDefault();
                S24_INT_M_COMMON.confirm({
                    message: `Reset defaults for this category?`,
                    accept: () => {
                        S24_INT_SETTINGS.handlePdReset(e.target.dataset.category);
                    },
                    cancel: () => { }
                });
                return;
            }

            if (e.target.matches('[data-btn-save-parcel-default]')) {
                e.preventDefault();
                S24_INT_SETTINGS.handlePdUpdate(e);
                return;
            }

            if (e.target.matches('[data-btn-cancel-parcel-default]')) {
                e.preventDefault();
                document.querySelector(S24_INT_SETTINGS.parcelDefaultModal).classList.add('hidden');
                return;
            }
        });
    },

    listenForPaginator: function () {
        const pdPaginator = document.querySelector('#s24_int_m_pd_pagination');

        if (!pdPaginator) {
            return;
        }

        pdPaginator.addEventListener('click', function (e) {
            if (
                e.target.matches('.s24_int_m-paginator-btn-previous')
                || e.target.matches('.s24_int_m-paginator-btn-next')
            ) {
                e.preventDefault();
                S24_INT_SETTINGS.loadCategoryPage(e.target.dataset.page);
            }
        });
    },

    openShippingMethodModal: function (e) {
        e.preventDefault();

        const dataset = e.target.dataset;
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
        const modalBody = modal.querySelector('.panel-body');
        const nameEl = modal.querySelector('input[name="option_name"]');
        const typeEl = modal.querySelector('input[name="option_type"]');
        const priceTypeEl = modal.querySelector('select[name="option_price_type"]');
        const countryTable = modal.querySelector('#sm-modal-countries');
        const apiCountriesWrapper = modal.querySelector('.api-countries-select-wrapper');

        // reset modal inputs to initial values
        modal.querySelectorAll('input[name="option_type"]').forEach(typeRadio => {
            typeRadio.disabled = false;
        });
        nameEl.value = '';
        modal.dataset.shippingOptionId = '';
        modal.classList.add('options-hidden');
        modal.querySelector('input[name="option_enabled"]').checked = false;
        modal.querySelector('input[name="option_sort_order"]').value = '';
        modal.querySelector('input[name="option_free_shipping"]').value = '';
        modal.querySelector('select[name="available_services"]').innerHTML = '';
        priceTypeEl.value = priceTypeEl.firstElementChild.value;

        // remove api countries select, will be added if its an edit action
        apiCountriesWrapper.innerHTML = '';

        // clean country table
        countryTable.innerHTML = '';
        if (S24_INT_SETTINGS.noCountryPlaceholderEl) {
            countryTable.append(S24_INT_SETTINGS.getCloneEl(S24_INT_SETTINGS.cloneList.noCountryPlaceholder));
        }

        modal.classList.remove('hidden', 'sm-create', 'sm-edit');

        nameEl.focus();

        S24_INT_SETTINGS.changePriceTypeAddon(S24_INT_SETTINGS.globalPriceTypeAddon, priceTypeEl.value);

        if (dataset.shippingMethodId) {
            // add api countries select
            apiCountriesWrapper.append(S24_INT_SETTINGS.getCloneEl(S24_INT_SETTINGS.cloneList.apiCountriesSelect));

            // countries select available only during editing
            jQuery('.sm-modal-api-countries select[name="api_countries"]').select2({
                width: 'resolve'
            });

            modal.classList.add('sm-edit');
            modal.dataset.shippingOptionId = dataset.shippingMethodId;

            // disable type selection
            modal.querySelectorAll('input[name="option_type"]').forEach(typeRadio => {
                typeRadio.disabled = true;
            });

            // call functions to load up custom data
            console.log('Loading custom shipping method data', dataset.shippingMethodId);
            S24_INT_SETTINGS.loadShippingMethod(dataset.shippingMethodId);

            return;
        }

        // if not editing mark as modal for creation (this hides country selector during creation)
        modal.classList.add('sm-create');
        typeEl.checked = true;
        S24_INT_SETTINGS.shippingMethodModalSelectType(typeEl.value, []);
    },

    loadShippingMethod: function (id) {
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
        const modalBody = modal.querySelector('.panel-body');

        const data = new FormData();
        data.set('shipping_option_id', id);

        S24_INT_M_COMMON.showLoadingOverlay(true, modalBody);
        fetch(S24_INT_M_SETTINGS_DATA.url_ajax + '&action=getShippingOption', {
            method: 'POST',
            body: data
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                if (json.data.error) {
                    alert(json.data.error);
                    return;
                }

                S24_INT_SETTINGS.fillShippingMethodModal(json.data.shipping_method);
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, modalBody);
            });
    },

    fillShippingMethodModal: function (data) {
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);

        modal.querySelector('input[name="option_enabled"]').checked = data.enabled == 1;
        modal.querySelector('input[name="option_sort_order"]').value = data.sort_order;
        modal.querySelector('input[name="option_name"]').value = data.title;
        modal.querySelector(`input[name="option_type"][value="${data.type}"]`).checked = true;

        modal.classList.remove('options-hidden');

        modal.querySelector('select[name="option_price_type"]').value = data.price_type;
        modal.querySelector('input[name="option_price"]').value = data.price;
        modal.querySelector('input[name="option_free_shipping"]').value = data.free_shipping;
        modal.querySelector(`input[name="option_price_priority"][value="${data.offer_priority}"]`).checked = true;

        let selectedServices = [];
        if (data.allowed_services) {
            selectedServices = data.allowed_services.split(',');
        }

        S24_INT_SETTINGS.changePriceTypeAddon(S24_INT_SETTINGS.globalPriceTypeAddon, data.price_type);

        S24_INT_SETTINGS.buildServicesSelector(data.type, selectedServices);

        Object.values(data.countries).forEach((country) => {
            S24_INT_SETTINGS.shippingMethodModalAddCountryRow(country);
        });
    },

    shippingMethodModalTypeSelected: function (e) {
        e.preventDefault();

        const type = parseInt(e.target.value);
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);

        S24_INT_SETTINGS.buildServicesSelector(type);

        // select first priority option as selected if modal is for creation
        if (modal.matches('.sm-create')) {
            modal.querySelector('input[name="option_price_priority"]').checked = true
        }

        modal.classList.remove('options-hidden');
    },

    shippingMethodModalSelectType: function (type) {
        const parsedType = parseInt(type);
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);

        S24_INT_SETTINGS.buildServicesSelector(parsedType);

        // select first priority option as selected if modal is for creation
        if (modal.matches('.sm-create')) {
            modal.querySelector('input[name="option_price_priority"]').checked = true
        }

        modal.classList.remove('options-hidden');
    },

    buildServicesSelector: function (type, selectedOptions) {
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
        const availableServices = modal.querySelector('select[name="available_services"]');
        const selectedServices = modal.querySelector('select[name="selected_services"]');

        availableServices.innerHTML = '';
        selectedServices.innerHTML = '';

        const serviceCodes = Object.keys(S24_INT_M_SETTINGS_DATA.servicesList);

        let html = '';
        let selectedHtml = '';
        serviceCodes.forEach(code => {
            if (S24_INT_M_SETTINGS_DATA.servicesList[code].shippingType !== type) {
                return;
            }

            const serviceCode = S24_INT_M_SETTINGS_DATA.services[code].service_code || '';
            let serviceAdditional = S24_INT_M_SETTINGS_DATA.services[code].additional || '';
            if (serviceAdditional !== '') {
                serviceAdditional = ` (${serviceAdditional})`;
            }
            const optionHtml = `<option value="${code}" style="--bg-logo: url(${S24_INT_M_SETTINGS_DATA.services[code].image})">[ ${serviceCode} ] ${S24_INT_M_SETTINGS_DATA.servicesList[code].name}${serviceAdditional}</option>`;

            if (selectedOptions && selectedOptions.some(item => item === code)) {
                selectedHtml += optionHtml;
                return;
            }

            html += optionHtml;
        });

        availableServices.innerHTML = html;
        selectedServices.innerHTML = selectedHtml;
    },

    shippingMethodModalServicesSelect: function (e) {
        e.preventDefault();
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
        const from = modal.querySelector(e.target.dataset.from);
        const to = modal.querySelector(e.target.dataset.to);

        const selectedOptions = from.selectedOptions;

        if (selectedOptions.length > 0) {
            to.append(...selectedOptions);
        }
    },

    changePriceTypeAddon: function (target, selectedType) {
        const addOnEl = document.querySelector(target);

        if (!addOnEl) {
            return;
        }

        addOnEl.textContent = S24_INT_M_SETTINGS_DATA.priceTypeAddons[selectedType] || '--';
    },

    shippingMethodModalAddCountryRow: function ({ country_code, free_shipping, offer_priority, price, price_type }) {
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);

        // do nothing if modal is closed
        if (modal.matches('.hidden')) {
            return;
        }

        const panelBody = document.querySelector(`#sm-modal-countries tr[data-country-code="${country_code}"]`);
        const countrySelector = modal.querySelector('select[name="api_countries"]');
        const countryTable = modal.querySelector('#sm-modal-countries');
        const placeholder = countryTable.querySelector('tr[data-placeholder]');

        console.log('Adding country', arguments);



        if (placeholder) {
            placeholder.remove();
        }

        let html = `
            <td data-cell-country></td>
            <td data-cell-priority></td>
            <td data-cell-price-type></td>
            <td data-cell-price></td>
            <td data-cell-free-shipping></td>
            <td data-cell-actions></td>
        `;

        const tr = document.createElement('tr');
        tr.dataset.countryCode = country_code;
        tr.dataset.countryName = '';
        tr.dataset.pricePriority = offer_priority;
        tr.dataset.priceType = price_type;
        tr.dataset.price = price;
        tr.dataset.freeShipping = free_shipping;
        tr.innerHTML = html;

        countryTable.append(tr);

        const countryOption = countrySelector.querySelector(`option[value="${country_code}"]`);
        if (countryOption) {
            countryOption.remove();
        }

        S24_INT_SETTINGS.shippingMethodModalCountryRowAction(country_code, false);
    },

    shippingMethodModalCountryUpdateRowData: function ({ country_code, free_shipping, offer_priority, price, price_type }) {
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
        const row = modal.querySelector(`#sm-modal-countries tr[data-country-code="${country_code}"]`);

        row.dataset.pricePriority = offer_priority;
        row.dataset.priceType = price_type;
        row.dataset.price = price;
        row.dataset.freeShipping = free_shipping;

        S24_INT_SETTINGS.shippingMethodModalCountryRowAction(country_code, false);
    },

    shippingMethodModalCountryRowAction: function (targetRow, isEdit = false) {
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
        const row = modal.querySelector(`#sm-modal-countries tr[data-country-code="${targetRow}"]`);

        if (!row) {
            console.log('NO ROW!');
            return;
        }

        console.log('FILLING ROW WITH DATA', row.dataset);

        let offerPriority = !row.dataset.pricePriority === '' || row.dataset.pricePriority === 'null' ? null : row.dataset.pricePriority;
        let priceType = !row.dataset.priceType || row.dataset.priceType === 'null' ? null : row.dataset.priceType;
        let freeShipping = !row.dataset.freeShipping || row.dataset.freeShipping === 'null' ? null : row.dataset.freeShipping;
        let price = !row.dataset.price || row.dataset.price === 'null' ? null : row.dataset.price;

        // default html
        let priorityHtml = offerPriority === null ? S24_INT_M_SETTINGS_DATA.strings.default : S24_INT_M_SETTINGS_DATA.pricePriorityType[offerPriority];
        let priceHtml = '';
        let priceTypeHtml = priceType === null ? S24_INT_M_SETTINGS_DATA.strings.default : `${price} ${S24_INT_M_SETTINGS_DATA.priceTypes[priceType]}`;
        let freeShippingHtml = freeShipping === null ? S24_INT_M_SETTINGS_DATA.strings.default : freeShipping;
        let actionButtonsHtml = `
            <button class="btn btn-s24 btn-s24-info s24-table-action-btn" 
                data-cell-btn-edit="${targetRow}" 
                data-toggle="tooltip" title="${S24_INT_M_SETTINGS_DATA.strings.edit}"
            >
                <img src="view/image/s24_int_m/country_price_edit.svg" alt="">
            </button>
            <button class="btn btn-s24 btn-s24-danger s24-table-action-btn" 
                data-cell-btn-delete="${targetRow}"
                data-toggle="tooltip" title="${S24_INT_M_SETTINGS_DATA.strings.delete}"
            >
                <img src="view/image/s24_int_m/country_price_trash.svg" alt="">
            </button>
        `;

        // for editing row html is different
        if (isEdit) {
            priorityHtml = ``;

            Object.keys(S24_INT_M_SETTINGS_DATA.pricePriorityType).forEach((key) => {
                const isSelected = (parseInt(key) === parseInt(offerPriority) || (parseInt(key) === 0 && offerPriority === null)) ? 'selected' : '';
                priorityHtml += `
                    <option value="${key}" ${isSelected}>${S24_INT_M_SETTINGS_DATA.pricePriorityType[key]}</option>
                `;
            });

            priorityHtml = `
                <select class="form-control">
                    ${priorityHtml}
                </select>
            `;

            priceTypeHtml = ``;

            Object.keys(S24_INT_M_SETTINGS_DATA.priceTypes).forEach((key) => {
                const isSelected = (parseInt(key) === parseInt(priceType) || (parseInt(key) === 0 && priceType === null)) ? 'selected' : '';
                priceTypeHtml += `
                    <option value="${key}" ${isSelected}>${S24_INT_M_SETTINGS_DATA.priceTypes[key]}</option>
                `;
            });

            let addonIdentifier = `priceType-${targetRow}`;

            priceTypeHtml = `
                <select class="form-control" data-price-type-selector-addon="${S24_INT_SETTINGS.shippingMethodModal} [data-price-type-addon='${addonIdentifier}']">
                    ${priceTypeHtml}
                </select>
            `;

            priceHtml = `
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="${S24_INT_M_SETTINGS_DATA.strings.type_in}"
                        value="${price === null ? '' : price}"
                    >
                    <span class="input-group-addon" data-price-type-addon="${addonIdentifier}">&euro;</span>
                </div>
            `;

            freeShippingHtml = `
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="${S24_INT_M_SETTINGS_DATA.strings.type_in}"
                        value="${freeShipping === null ? '' : freeShipping}"
                    >
                    <span class="input-group-addon">&euro;</span>
                </div>
            `;

            actionButtonsHtml = `
                <button class="btn btn-s24 btn-s24-primary s24-table-action-btn" 
                    data-cell-btn-save="${targetRow}"
                    data-toggle="tooltip" title="${S24_INT_M_SETTINGS_DATA.strings.save}"
                >
                    <img src="view/image/s24_int_m/country_price_save.svg" alt="">
                </button>
                <button class="btn btn-s24 btn-s24-info s24-table-action-btn" 
                    data-cell-btn-cancel="${targetRow}"
                    data-toggle="tooltip" title="${S24_INT_M_SETTINGS_DATA.strings.cancel}"
                >
                    <img src="view/image/s24_int_m/country_price_cancel.svg" alt="">
                </button>
            `;
        }

        const country = S24_INT_SETTINGS.findCountryData(row.dataset.countryCode);

        actionButtonsHtml = `
            <div class="s24-sm-actions-wrapper">
                ${actionButtonsHtml}
            </div>
        `;

        row.querySelector('[data-cell-country]').innerHTML = country ? country.en_name : 'UNKNOWN';
        row.querySelector('[data-cell-priority]').innerHTML = priorityHtml;
        row.querySelector('[data-cell-price-type]').innerHTML = priceTypeHtml;
        row.querySelector('[data-cell-price]').innerHTML = priceHtml;
        row.querySelector('[data-cell-free-shipping]').innerHTML = freeShippingHtml;
        row.querySelector('[data-cell-actions]').innerHTML = actionButtonsHtml;

        S24_INT_SETTINGS.attachBootstrapTooltip(row);

        if (isEdit) {
            S24_INT_SETTINGS.changePriceTypeAddon(`${S24_INT_SETTINGS.shippingMethodModal} #sm-modal-countries tr[data-country-code='${targetRow}'] td[data-cell-price] .input-group-addon`, priceType);
        }
    },

    findCountryData: function (countryCode) {
        return S24_INT_M_SETTINGS_DATA.api_coutries.find((item) => item.code === countryCode);
    },

    saveOptionCountry: function (data, loadingOn, callback) {
        // const panelBody = document.querySelector('#shipping_option_modal .panel-body');
        const formData = new FormData();

        Object.keys(data).forEach((key) => {
            formData.set(key, data[key]);
        });

        S24_INT_M_COMMON.showLoadingOverlay(true, loadingOn);
        fetch(S24_INT_M_SETTINGS_DATA.url_ajax + '&action=saveOptionCountry', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                if (!json.data.update_result) {
                    let message = json.data.error ? json.data.error : 'Unexpected error while trying to update shipping option country data';
                    alert(message);
                    return;
                }

                callback(json.data);
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, loadingOn);
            });
    },

    deleteOptionCountry: function (optionId, countryCode, callback) {
        const panelBody = document.querySelector(`#sm-modal-countries tr[data-country-code="${countryCode}"]`);
        const formData = new FormData();

        formData.set('option_id', optionId);
        formData.set('country_code', countryCode);

        S24_INT_M_COMMON.showLoadingOverlay(true, panelBody);
        fetch(S24_INT_M_SETTINGS_DATA.url_ajax + '&action=deleteOptionCountry', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                if (!json.data.update_result) {
                    let message = json.data.error ? json.data.error : 'Unexpected error while trying to delete shipping option country data';
                    alert(message);
                    return;
                }

                callback();
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, panelBody);
            });
    },

    handleGlobalPdUpdate: function (e) {
        e.preventDefault();

        const fields = {
            category_id: '#input-pd-category-id',
            hs_code: '#input-pd-hs-code',
            weight: '#input-pd-weight',
            length: '#input-pd-length',
            height: '#input-pd-height',
            width: '#input-pd-width'
        };

        const panel = document.querySelector('#tab-parcel-defaults');

        this.updateParcelDefault(fields, panel);
    },

    getPdModalFields: function () {
        return {
            category_id: '#modal-pd-category-id',
            hs_code: '#modal-pd-hs-code',
            weight: '#modal-pd-weight',
            length: '#modal-pd-length',
            height: '#modal-pd-height',
            width: '#modal-pd-width'
        };
    },

    fillPdModal: function (dataset) {
        const modal = document.querySelector(S24_INT_SETTINGS.parcelDefaultModal);

        const fieldsMap = S24_INT_SETTINGS.getPdModalFields();
        const fields = Object.keys(fieldsMap);

        const title = modal.querySelector('#parcel_default_modal_title');

        if (dataset.hasDefaults === 'false') {
            console.log('Using global defaults');
            fields.forEach(key => {
                if (key === 'category_id') {
                    return;
                }
                modal.querySelector(fieldsMap[key]).value = S24_INT_M_SETTINGS_DATA.globalParcelDefault[key];
            });
        } else {
            console.log('Using custom defaults');
            fields.forEach(key => {
                if (key === 'category_id') {
                    return;
                }
                modal.querySelector(fieldsMap[key]).value = dataset[key];
            });
        }

        modal.querySelector(fieldsMap['category_id']).value = dataset.category;
        title.textContent = dataset.categoryName;

        modal.classList.remove('hidden');
        modal.querySelector(fieldsMap.weight).focus();
    },

    handlePdUpdate: function (e) {
        const panel = document.querySelector(S24_INT_SETTINGS.parcelDefaultModal);
        this.updateParcelDefault(S24_INT_SETTINGS.getPdModalFields(), panel);
    },

    handlePdReset: function (categoryId) {
        const panelBody = document.querySelector('#tab-parcel-defaults .panel-body');
        const data = new FormData();

        data.set('category_id', categoryId);

        S24_INT_M_COMMON.showLoadingOverlay(true, panelBody);
        fetch(S24_INT_M_SETTINGS_DATA.url_ajax + '&action=resetPdCategory', {
            method: 'POST',
            body: data
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                if (!json.data.update_result) {
                    let message = json.data.error ? json.data.error : 'Unexpected error while trying to reset category parcel defaults';
                    alert(message);
                    return;
                }

                // update page
                let currentPage = document.querySelector('#s24_int_m_pd_pagination .s24_int_m_current_page');
                if (currentPage) {
                    currentPage = currentPage.innerText.trim();
                } else {
                    currentPage = 1;
                }
                S24_INT_SETTINGS.loadCategoryPage(currentPage);
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, panelBody);
            });
    },

    loadCategoryPage: function (page) {
        const pdCategories = document.querySelector('#pd-categories');
        const pdPagination = document.querySelector('#s24_int_m_pd_pagination');
        const panelBody = pdCategories.closest('.panel-body');

        S24_INT_M_COMMON.showLoadingOverlay(true, panelBody);
        fetch(S24_INT_M_SETTINGS_DATA.url_ajax + '&action=getPdCategories&page=' + page)
            .then(res => res.json())
            .then(json => {
                if (!json.data) {
                    return;
                }

                pdCategories.innerHTML = json.data.pd_categories_partial;
                pdPagination.innerHTML = json.data.pd_categories_paginator;

                if (typeof $.fn.tooltip === 'function') {
                    S24_INT_SETTINGS.attachBootstrapTooltip(pdCategories);
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, panelBody);
            });
    },

    updateParcelDefault: function (fields, panel) {
        const panelBody = panel.querySelector('.panel-body');
        const data = new FormData();

        data.set('category_id', panelBody.querySelector(fields.category_id).value);
        data.set('hs_code', panelBody.querySelector(fields.hs_code).value);
        data.set('weight', panelBody.querySelector(fields.weight).value);
        data.set('length', panelBody.querySelector(fields.length).value);
        data.set('height', panelBody.querySelector(fields.height).value);
        data.set('width', panelBody.querySelector(fields.width).value);

        // remove previously marked errors from Parcel Defaults page
        panelBody.querySelectorAll('.has-error').forEach(item => item.classList.remove('has-error'));

        S24_INT_M_COMMON.showLoadingOverlay(true, panelBody);
        fetch(S24_INT_M_SETTINGS_DATA.url_ajax + '&action=savePdCategory', {
            method: 'POST',
            body: data
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                const validationFields = Object.keys(json.data.validation);

                validationFields.forEach(fieldKey => {
                    if (!json.data.validation[fieldKey]) {
                        panelBody.querySelector(fields[fieldKey]).parentNode.classList.add('has-error')
                    }
                });

                if (json.data.update_result && json.data.parcel_default && json.data.parcel_default.category_id === 0) {
                    S24_INT_M_SETTINGS_DATA.globalParcelDefault = json.data.parcel_default;
                }

                // close modal on success
                if (json.data.update_result && panel.matches('.s24-int-m-modal')) {
                    panel.classList.add('hidden');
                    let currentPage = document.querySelector('#s24_int_m_pd_pagination .s24_int_m_current_page');
                    if (currentPage) {
                        currentPage = currentPage.innerText.trim();
                    } else {
                        currentPage = 1;
                    }
                    S24_INT_SETTINGS.loadCategoryPage(currentPage);
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, panelBody);
            });
    },

    saveShippingOption: function () {
        const modal = document.querySelector(S24_INT_SETTINGS.shippingMethodModal);
        const panel = modal.querySelector('.panel');

        const data = {
            title: modal.querySelector('input[name="option_name"]').value,
            enabled: modal.querySelector('input[name="option_enabled"]').checked ? 1 : 0,
            type: modal.querySelector('input[name="option_type"]:checked').value,
            allowed_services: [...modal.querySelector('select[name="selected_services"]').options].map(item => item.value).join(','),
            offer_priority: modal.querySelector('[name="option_price_priority"]:checked').value,
            sort_order: modal.querySelector('input[name="option_sort_order"]').value,
            price_type: modal.querySelector('select[name="option_price_type"]').value,
            price: modal.querySelector('input[name="option_price"]').value,
            free_shipping: modal.querySelector('input[name="option_free_shipping"]').value
        };

        const formData = new FormData();

        if (modal.dataset.shippingOptionId && modal.dataset.shippingOptionId != '') {
            formData.set('option_id', parseInt(modal.dataset.shippingOptionId));
        }

        Object.keys(data).forEach(key => {
            formData.set(key, data[key]);
        });

        console.log('Shipping method data to save', parseInt(modal.dataset.shippingOptionId), data);

        S24_INT_M_COMMON.showLoadingOverlay(true, panel);
        fetch(S24_INT_M_SETTINGS_DATA.url_ajax + '&action=saveShippingOption', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                // close modal on success
                if (json.data.update_result) {
                    modal.classList.add('hidden');
                    if (!json.data.shipping_options) {
                        return;
                    }

                    document.querySelector('#shipping_options').innerHTML = json.data.shipping_options;
                    S24_INT_SETTINGS.renderShippingServices();
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, panel);
            });
    },

    deleteShippingOption: function (optionId) {
        const shippingMethodPanel = document.querySelector(`#tab-shipping-method .panel`);
        const formData = new FormData();

        formData.set('option_id', optionId);

        S24_INT_M_COMMON.showLoadingOverlay(true, shippingMethodPanel);
        fetch(S24_INT_M_SETTINGS_DATA.url_ajax + '&action=deleteShippingOption', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                // remove shipping option line from table on success
                if (json.data.update_result) {
                    const shippingMethodTr = document.querySelector(`#tab-shipping-method tr[data-shipping-method-id="${optionId}"]`);
                    if (shippingMethodTr) {
                        shippingMethodTr.remove();
                    }
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, shippingMethodPanel);
            });
    },

    renderShippingServices: function () {
        document.querySelectorAll('[data-shipping-method-id]').forEach(smEl => {
            S24_INT_SETTINGS.buildServicesDisplay(smEl);
        });
    },

    buildServicesDisplay: function (rowEl) {
        const serviceIds = (rowEl.dataset.services || '').split(',');
        const servicesTd = rowEl.querySelector('[data-services-row]');

        if (!servicesTd) {
            return;
        }

        let count = 0;
        let htmlArray = [];
        const hasManyServices = serviceIds.length > 3;
        serviceIds.forEach(id => {
            const service_data = S24_INT_M_SETTINGS_DATA.services[id] || null;
            if (!service_data) {
                return;
            }
            if (hasManyServices && count === 2) {
                htmlArray.push(`<button data-expand-section class="s24-service-pill" data-count="+${serviceIds.length - 2}"></button>`);
            }
            ++count;
            htmlArray.push(`
            <div class="s24-service-pill">
                ` + (service_data.image ? `<image src="${service_data.image}" alt="Service logo">` : '') + `
                ${service_data.name}
            </div>
            `);
        });

        if (hasManyServices) {
            const template = document.querySelector(`[data-template="sm-section-hide"]`).innerHTML;
            htmlArray.push(`
                <button data-expand-section class="close-section-btn">${template}</button>              
            `);
        }

        servicesTd.innerHTML = `<section data-expanded="0">${htmlArray.join("")}</section>`;
    },

    attachBootstrapTooltip: function (root) {
        if (typeof $.fn.tooltip !== 'function') {
            return;
        }

        $(root).find('[data-toggle="tooltip"]').tooltip();
    }
}

document.addEventListener('DOMContentLoaded', function (e) {
    S24_INT_SETTINGS.init();
});