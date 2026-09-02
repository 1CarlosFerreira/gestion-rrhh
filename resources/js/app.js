import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('replacementReview', (initial) => ({
    saving: false,
    approving: false,
    approvalOpen: false,
    approvalError: '',
    recoveryUrl: '',
    fieldErrors: {},
    grado: initial.grado,
    clasificacion: initial.clasificacion,
    normativa: initial.normativa,
    approvable: initial.approvable === true,
    documentsReady: initial.documentsReady !== false,
    returnOpen: false,

    get validGrade() {
        const value = Number(this.grado);

        return Number.isInteger(value) && value > 0;
    },

    get validClassification() {
        return this.hasExplicitValue(this.clasificacion);
    },

    get normativeSelected() {
        return this.hasExplicitValue(this.normativa)
            && ['0', '1', 'false', 'true'].includes(String(this.normativa));
    },

    get missingApprovalFields() {
        const missing = [];

        if (!this.validGrade) missing.push('falta ingresar un último grado E.U.S. válido');
        if (!this.validClassification) missing.push('falta seleccionar la clasificación de área');
        if (!this.normativeSelected) missing.push('falta indicar el cumplimiento de normativa');
        if (!this.documentsReady) missing.push('faltan requisitos documentales obligatorios');

        return missing;
    },

    openApproval() {
        this.approvalError = '';
        this.recoveryUrl = '';
        this.fieldErrors = {};

        if (this.missingApprovalFields.length) {
            if (!this.validGrade) this.fieldErrors.grado_eus_informado = ['Ingrese un último grado E.U.S. válido.'];
            if (!this.validClassification) this.fieldErrors.clasificacion_area_id = ['Seleccione una clasificación de área.'];
            if (!this.normativeSelected) this.fieldErrors.cumple_normativa = ['Indique si cumple la normativa.'];
            this.approvalError = `No puedes aprobar: ${this.missingApprovalFields.join('; ')}.`;
            this.$nextTick(() => this.focusFirstInvalid());
            return;
        }

        this.approvalOpen = true;
    },

    async approve() {
        if (this.approving || this.saving) return;

        const form = this.$refs.revisionForm;
        this.approvalError = '';
        this.recoveryUrl = '';
        this.fieldErrors = {};

        if (this.missingApprovalFields.length || !form.reportValidity()) {
            this.approvalOpen = false;
            this.$nextTick(() => this.focusFirstInvalid());
            return;
        }

        this.approving = true;

        try {
            const payload = new FormData(form);
            payload.set('accion', 'aprobar');
            const response = await fetch(form.action, {
                method: 'POST',
                body: payload,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const body = await response.json().catch(() => null);

            if (response.status === 422) {
                this.fieldErrors = body?.errors ?? {};
                this.approvalError = body?.message ?? 'Revisa los antecedentes ingresados.';
                this.approvalOpen = false;
                this.$nextTick(() => this.focusFirstInvalid());
                return;
            }

            if (!response.ok) {
                this.approvalError = body?.message ?? this.httpErrorMessage(response.status);
                this.recoveryUrl = body?.redirect ?? '';
                this.approvalOpen = false;
                return;
            }

            window.location.assign(body?.redirect ?? response.url);
        } catch (error) {
            this.approvalError = 'No fue posible comunicarse con el servidor. Revisa tu conexión e intenta nuevamente.';
            this.approvalOpen = false;
        } finally {
            this.approving = false;
        }
    },

    focusFirstInvalid() {
        const field = Object.keys(this.fieldErrors)[0]
            ?? (this.missingApprovalFields.length ? ['grado_eus_informado', 'clasificacion_area_id', 'cumple_normativa'].find((name) => {
                const value = name === 'grado_eus_informado' ? this.grado : (name === 'clasificacion_area_id' ? this.clasificacion : this.normativa);
                return value === '';
            }) : null);

        if (!field) return;
        const control = this.$refs.revisionForm.querySelector(`[name="${field}"]`);
        control?.focus();
        control?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },

    httpErrorMessage(status) {
        if (status === 403) return 'No tienes autorización para aprobar esta solicitud.';
        if (status === 419) return 'La sesión expiró. Recarga la página e intenta nuevamente.';

        return 'No fue posible aprobar y generar el PDF. Intenta nuevamente.';
    },

    hasExplicitValue(value) {
        return value !== null && value !== undefined && value !== '';
    },
}));

Alpine.start();
