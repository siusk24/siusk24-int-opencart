var S24_INT_M_ORDER = {
    wrapper: null,
    labelResponse: null,
    terminals: null,

    init: function () {
        this.addOrderInformationPanel();
        this.addGlobalListeners();
    },

    addGlobalListeners: function () {
        S24_INT_M_COMMON.addGlobalListener('click', '[data-register-shipment-btn]', (e) => {
            e.preventDefault();

            if (S24_INT_M_ORDER.terminalChanged()) {
                const warning_template = document.querySelector('#s24-terminal-change-not-saved-warning');
                S24_INT_M_COMMON.confirm({
                    message: warning_template.content.cloneNode(true).textContent,
                    accept: () => {
                        S24_INT_M_ORDER.handleTerminalChangeAction();
                        S24_INT_M_ORDER.handleRegisterShipmentAction();
                    }
                });
                return;
            }

            S24_INT_M_ORDER.handleRegisterShipmentAction();
        });

        S24_INT_M_COMMON.addGlobalListener('click', '[data-cancel-shipment-btn]', (e) => {
            e.preventDefault();

            S24_INT_M_COMMON.confirm({
                message: e.target.dataset.warning,
                accept: () => {
                    S24_INT_M_ORDER.cancelShipment(e.target.dataset.cancelShipmentBtn);
                },
                cancel: () => { }
            });
        });

        S24_INT_M_COMMON.addGlobalListener('click', '[data-get-label-btn]', (e) => {
            e.preventDefault();

            if (S24_INT_M_ORDER.labelResponse && S24_INT_M_ORDER.labelResponse.base64pdf) {
                S24_INT_M_COMMON.downloadPdf(S24_INT_M_ORDER.labelResponse.base64pdf, e.target.dataset.getLabelBtn);
                return;
            }

            S24_INT_M_ORDER.getLabel(e.target.dataset.getLabelBtn);
        });

        S24_INT_M_COMMON.addGlobalListener('click', '[data-btn-terminal-change-save]', (e) => {
            e.preventDefault();

            S24_INT_M_ORDER.saveTerminal();
        });

        S24_INT_M_COMMON.addGlobalListener('click', '[data-terminal-change-btn]', (e) => {
            e.preventDefault();

            if (S24_INT_M_ORDER.terminalChanged()) {
                const warning_template = document.querySelector('#s24-terminal-change-not-saved-warning');
                S24_INT_M_COMMON.confirm({
                    message: warning_template.content.cloneNode(true).textContent,
                    accept: () => {
                        S24_INT_M_ORDER.handleTerminalChangeAction();
                    }
                });
                return;
            }

            S24_INT_M_ORDER.handleTerminalChangeAction();
        });
    },

    handleRegisterShipmentAction: function () {
        const terminalLine = S24_INT_M_ORDER.wrapper.querySelector('[data-terminal]');
        if (terminalLine && !terminalLine.dataset.terminalSelected) {
            S24_INT_M_COMMON.alert({
                message: S24_INT_M_ORDER.wrapper.querySelector('input[name="select_terminal"]').value
            });
            return;
        }

        S24_INT_M_ORDER.registerShipment();
    },

    handleTerminalChangeAction: function () {
        const state = S24_INT_M_ORDER.toggleTerminalSelector();
        if (state === 'closed') {
            return;
        }

        S24_INT_M_ORDER.loadChangeTerminal();
    },

    addOrderInformationPanel: function () {
        const historyPanel = document.querySelector('#history').closest('.panel');

        const wrapper = document.createElement('div');
        wrapper.id = 's24_int_m_panel';
        wrapper.classList.add('s24_int_m_content');

        historyPanel.parentNode.insertBefore(wrapper, historyPanel);

        S24_INT_M_ORDER.wrapper = wrapper;

        this.loadOrderInformationPanel(wrapper);
    },

    loadOrderInformationPanel: function (wrapper) {
        const formData = new FormData();
        formData.set('order_id', S24_INT_M_ORDER_DATA.order_id);

        S24_INT_M_COMMON.showLoadingOverlay(true, wrapper);
        fetch(S24_INT_M_ORDER_DATA.ajax + '&action=getOrderPanel', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                if (json.data.error) {
                    wrapper.innerHTML = `
                        <div class="alert alert-danger">
                            ${json.data.error}
                        </div>
                    `;
                    return;
                }

                if (json.data.label_response) {
                    S24_INT_M_ORDER.labelResponse = json.data.label_response;
                }

                if (json.data.panelHtml) {
                    wrapper.innerHTML = json.data.panelHtml;
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, wrapper);
            });
    },

    registerShipment: function () {
        const formData = new FormData();
        formData.set('order_id', S24_INT_M_ORDER_DATA.order_id);

        S24_INT_M_COMMON.showLoadingOverlay(true, S24_INT_M_ORDER.wrapper);
        fetch(S24_INT_M_ORDER_DATA.ajax + '&action=registerShipment', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                if (json.data.error) {
                    S24_INT_M_COMMON.alert({
                        message: json.data.error
                    });
                    return;
                }

                S24_INT_M_COMMON.alert({
                    message: 'Shipment registered',
                    onClose: () => {
                        S24_INT_M_ORDER.loadOrderInformationPanel(S24_INT_M_ORDER.wrapper);
                    }
                });
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, S24_INT_M_ORDER.wrapper);
            });
    },

    cancelShipment: function (shipment_id) {
        if (!shipment_id) {
            S24_INT_M_COMMON.alert({
                message: 'Something is wrong, no Shipment ID is detected'
            });
            return;
        }

        const formData = new FormData();
        formData.set('order_id', S24_INT_M_ORDER_DATA.order_id);
        formData.set('shipment_id', shipment_id);

        S24_INT_M_COMMON.showLoadingOverlay(true, S24_INT_M_ORDER.wrapper);
        fetch(S24_INT_M_ORDER_DATA.ajax + '&action=cancelShipment', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                const responseEl = document.querySelector('[data-api-response]');

                if (json.data.error) {
                    responseEl.innerHTML = json.data.error;
                    return;
                }

                if (json.data.canceled_result && json.data.canceled_result.status == 'deleted') {
                    S24_INT_M_COMMON.alert({
                        message: 'Shipment canceled',
                        onClose: () => {
                            S24_INT_M_ORDER.loadOrderInformationPanel(S24_INT_M_ORDER.wrapper);
                        }
                    });
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, S24_INT_M_ORDER.wrapper);
            });
    },

    getLabel: function (shipmentId) {
        const formData = new FormData();
        formData.set('order_id', S24_INT_M_ORDER_DATA.order_id);

        S24_INT_M_COMMON.showLoadingOverlay(true, S24_INT_M_ORDER.wrapper);
        fetch(S24_INT_M_ORDER_DATA.ajax + '&action=getLabel', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                if (json.data.error) {
                    S24_INT_M_COMMON.alert({
                        message: json.data.error
                    });
                    return;
                }

                if (json.data.response) {
                    S24_INT_M_ORDER.labelResponse = json.data.response;

                    if (json.data.response.base64pdf) {
                        S24_INT_M_COMMON.downloadPdf(json.data.response.base64pdf, shipmentId);
                    }
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, S24_INT_M_ORDER.wrapper);
            });
    },

    loadChangeTerminal: function () {
        const terminalLine = S24_INT_M_ORDER.wrapper.querySelector('[data-terminal]');
        const selectorWrapper = S24_INT_M_ORDER.wrapper.querySelector('[data-terminal-selector-wrapper]');
        const apiUrl = S24_INT_M_ORDER.wrapper.querySelector('input[name="api_url"]').value;
        let countryCode = terminalLine.dataset.terminalCountry;
        let postCode = terminalLine.dataset.terminalPostcode;
        let identifier = terminalLine.dataset.terminalIdentifier;
        let selectedId = terminalLine.dataset.terminalSelected;

        S24_INT_M_COMMON.showLoadingOverlay(true, S24_INT_M_ORDER.wrapper);

        S24_INT_M_ORDER.getTerminals({ apiUrl, countryCode, postCode, identifier })
            .then(terminals => {
                const select = document.createElement('select');
                select.dataset.terminalSelect = true;
                select.style.width = '100%';
                let options = '';
                terminals.forEach(terminal => {
                    const selected = terminal.id == selectedId ? 'selected' : '';
                    options += `
                        <option value="${terminal.id}" ${selected}>${terminal.id} - ${terminal.name}, ${terminal.address}</option>
                    `;
                });
                select.innerHTML = options;
                selectorWrapper.append(select);

                jQuery('[data-terminal-select]').select2({
                    width: 'resolve'
                });

                // need to listen using jQuery due to how select2 triggers events
                jQuery('[data-terminal-select]').on('change', function (e) {
                    S24_INT_M_ORDER.terminalChanged();
                });

                S24_INT_M_ORDER.terminalChanged();
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, S24_INT_M_ORDER.wrapper);
            });
    },

    getTerminals: async function ({ apiUrl, countryCode, postCode, identifier, selected }) {
        if (S24_INT_M_ORDER.terminals) {
            return S24_INT_M_ORDER.terminals;
        }

        const terminals = await S24_INT_M_ORDER.getTerminalsFromApi({ apiUrl, countryCode, postCode, identifier })

        S24_INT_M_ORDER.terminals = terminals;
        return terminals;
    },

    getTerminalsFromApi: async function ({ apiUrl, countryCode, postCode, identifier }) {
        try {
            const response = await fetch(`${apiUrl}/parcel_machines?q[country_code_eq]=${countryCode}&q[identifier_eq]=${identifier}&receiver_address=${postCode}`);
            const terminals = await response.json();

            if (terminals?.result?.parcel_machines) {
                return terminals.result.parcel_machines;
            }
        } catch (error) {
            return [];
        }

        return [];
    },

    terminalChanged: function () {
        const terminalLine = S24_INT_M_ORDER.wrapper.querySelector('[data-terminal]');
        const terminalSaveBtn = S24_INT_M_ORDER.wrapper.querySelector('[data-btn-terminal-change-save]');
        const selector = S24_INT_M_ORDER.wrapper.querySelector('select[data-terminal-select]');

        if (!selector) {
            return false;
        }

        // check inverse if changed (changed = false, not changed = true)
        const hasChanged = selector.value == terminalLine.dataset.terminalSelected || !S24_INT_M_ORDER.terminals;

        terminalSaveBtn.disabled = hasChanged;

        return !hasChanged; // return normalized answer so if no changes should return false;
    },

    toggleTerminalSelector: function () {
        const panelFooter = S24_INT_M_ORDER.wrapper.querySelector('[data-terminal-change-open]');
        const selectorWrapper = S24_INT_M_ORDER.wrapper.querySelector('[data-terminal-selector-wrapper]');

        if (panelFooter.dataset.terminalChangeOpen === 'open') {
            panelFooter.dataset.terminalChangeOpen = 'closed';

            if (selectorWrapper) {
                selectorWrapper.innerHTML = '';
            }
            return panelFooter.dataset.terminalChangeOpen;
        }

        if (selectorWrapper) {
            selectorWrapper.innerHTML = '';
        }
        panelFooter.dataset.terminalChangeOpen = 'open';

        return panelFooter.dataset.terminalChangeOpen;
    },

    saveTerminal: function () {
        const terminalLine = S24_INT_M_ORDER.wrapper.querySelector('[data-terminal]');
        const selector = S24_INT_M_ORDER.wrapper.querySelector('select[data-terminal-select]');

        if (selector.value == terminalLine.dataset.terminalSelected || !S24_INT_M_ORDER.terminals) {
            S24_INT_M_ORDER.toggleTerminalSelector();
            console.log('Nothing changed...');
            return;
        }

        S24_INT_M_COMMON.showLoadingOverlay(true, S24_INT_M_ORDER.wrapper);

        const selectedTerminal = S24_INT_M_ORDER.terminals.find(terminal => {
            return terminal.id == selector.value;
        });

        if (!selectedTerminal) {
            S24_INT_M_ORDER.toggleTerminalSelector();
            S24_INT_M_COMMON.showLoadingOverlay(false, S24_INT_M_ORDER.wrapper);
            return;
        }

        const formData = new FormData();
        formData.set('order_id', S24_INT_M_ORDER_DATA.order_id);
        Object.keys(selectedTerminal).forEach(key => {
            formData.set(key === 'id' ? 'terminal_id' : key, selectedTerminal[key]);
        });

        fetch(S24_INT_M_ORDER_DATA.ajax + '&action=updateSelectedTerminal', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(json => {
                console.log(json);

                if (!json.data) {
                    return;
                }

                if (json.data.error) {
                    S24_INT_M_COMMON.alert({
                        message: json.data.error
                    });
                    return;
                }

                if (json.data?.result) {
                    const terminalInfo = terminalLine.querySelector('[data-terminal-info-text]');
                    terminalLine.dataset.terminalSelected = selectedTerminal.id;
                    terminalInfo.innerText = `${selectedTerminal.id} - ${selectedTerminal.name}, ${selectedTerminal.address}`;
                } else {
                    S24_INT_M_COMMON.alert({
                        message: 'Something went wrong while saving terminal information'
                    });
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, S24_INT_M_ORDER.wrapper);
                S24_INT_M_ORDER.toggleTerminalSelector();
            });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    S24_INT_M_ORDER.init();
});