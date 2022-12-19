var S24_INT_M_MANIFEST = {
    init: function () {
        this.addGlobalListeners();
        S24_INT_M_COMMON.showLoadingOverlay(false, document.querySelector('#content'));
    },

    addGlobalListeners: function () {
        S24_INT_M_COMMON.addGlobalListener('click', '[data-print-manifest]', (e) => {
            e.preventDefault();

            const warning_template = document.querySelector('#s24-manifest-print-warning');

            S24_INT_M_COMMON.confirm({
                message: warning_template.content.cloneNode(true).textContent,
                accept: () => {
                    console.log('Generate manifest', e.target.dataset.printManifest);
                    S24_INT_M_MANIFEST.getManifest(e.target.dataset.printManifest);
                }
            });
        });

        S24_INT_M_COMMON.addGlobalListener('click', '[data-page]', (e) => {
            e.preventDefault();
            S24_INT_M_MANIFEST.loadManifestPage(e.target.dataset.page);
        });
    },

    loadManifestPage: function (page) {
        console.log(page);
        const panel = document.querySelector('#s24_int_m-panel');
        const tableWrapper = document.querySelector('#manifest-list-table');
        const formData = new FormData();
        formData.set('page', page);

        S24_INT_M_COMMON.showLoadingOverlay(true, panel);
        fetch(S24_INT_M_MANIFEST_DATA.ajax + '&action=loadManifestPage', {
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

                if (json.data.html) {
                    tableWrapper.innerHTML = json.data.html;
                    S24_INT_M_MANIFEST.attachBootstrapTooltip(tableWrapper);
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, panel);
            });
    },

    getManifest: function (manifestId) {
        const panel = document.querySelector('#s24_int_m-panel');
        const formData = new FormData();
        formData.set('manifest_id', manifestId);

        S24_INT_M_COMMON.showLoadingOverlay(true, panel);
        fetch(S24_INT_M_MANIFEST_DATA.ajax + '&action=getManifest', {
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

                if (json.data.response && json.data.response.manifest == null) {
                    S24_INT_M_COMMON.alert({
                        message: 'Manifest is not ready. Please try again later'
                    });
                }

                if (json.data.response && json.data.response.manifest) {
                    S24_INT_M_COMMON.downloadPdf(json.data.response.manifest, manifestId + '_manifest');
                }

                // if response has labels data ask if user wants it
                if (json.data.response && json.data.response.labels) {
                    S24_INT_M_COMMON.confirm({
                        message: 'Download manifest labels?',
                        accept: () => {
                            S24_INT_M_COMMON.downloadPdf(json.data.response.labels, manifestId + '_labels');
                        }
                    });
                }
            })
            .finally(() => {
                S24_INT_M_COMMON.showLoadingOverlay(false, panel);
            });
    },

    attachBootstrapTooltip: function (root) {
        if (typeof $.fn.tooltip !== 'function') {
            return;
        }

        $(root).find('[data-toggle="tooltip"]').tooltip();
    }
}

document.addEventListener('DOMContentLoaded', function (e) {
    S24_INT_M_MANIFEST.init();
});