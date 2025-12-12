/**
 * WhatsApp Evolution Clients - intl-tel-input inicialização
 * 
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @created 2025-12-11 09:49:22
 */

(function ($) {
    'use strict';

    /**
     * Inicializador do campo de telefone
     */
    const WECPhone = {
        iti: null,
        $input: null,
        $hidden: null,

        /**
         * Inicialização
         */
        init: function () {
            this.$input = $('#wec_whatsapp');
            this.$hidden = $('#wec_whatsapp_e164');

            if (!this.$input.length) {
                return;
            }

            this.initIntlTelInput();
            this.bindEvents();
        },

        /**
         * Inicializa intl-tel-input
         */
        initIntlTelInput: function () {
            const input = this.$input[0];

            // Verificar se utils está carregado
            if (typeof intlTelInput === 'undefined') {
                console.error('intl-tel-input não está carregado');
                return;
            }

            // Inicializar
            this.iti = intlTelInput(input, {
                initialCountry: 'br',
                preferredCountries: ['br', 'us', 'pt', 'es', 'ar'],
                separateDialCode: true,
                nationalMode: false,
                formatOnDisplay: true,
                utilsScript: wecPhoneConfig.utilsPath,
                customPlaceholder: function (selectedCountryPlaceholder, selectedCountryData) {
                    return selectedCountryPlaceholder;
                },
                // Validação customizada
                customContainer: 'wec-intl-phone-container'
            });

            // Se já tem valor, setar
            const currentValue = this.$hidden.val();
            if (currentValue) {
                this.iti.setNumber(currentValue);
            }
        },

        /**
         * Bind de eventos
         */
        bindEvents: function () {
            const self = this;

            // Ao alterar o input
            this.$input.on('change blur keyup', function () {
                self.updateHiddenField();
                self.validatePhone();
            });

            // Ao alterar o país
            this.$input.on('countrychange', function () {
                self.updateHiddenField();
                self.validatePhone();
            });

            // Antes de submeter o form
            $('form#post').on('submit', function () {
                self.updateHiddenField();
            });
        },

        /**
         * Atualiza o campo hidden com valor E.164
         */
        updateHiddenField: function () {
            if (!this.iti) return;

            // Obter número em formato E.164
            const number = this.iti.getNumber();
            this.$hidden.val(number);
        },

        /**
         * Valida o telefone
         */
        validatePhone: function () {
            if (!this.iti) return true;

            const $wrapper = this.$input.closest('.wec-phone-wrapper');
            const number = this.$input.val().trim();

            // Se vazio, não validar
            if (!number) {
                $wrapper.find('.iti').removeClass('iti--valid iti--invalid');
                return true;
            }

            // Validar
            const isValid = this.iti.isValidNumber();

            if (isValid) {
                $wrapper.find('.iti').removeClass('iti--invalid').addClass('iti--valid');
            } else {
                $wrapper.find('.iti').removeClass('iti--valid').addClass('iti--invalid');
            }

            return isValid;
        },

        /**
         * Validação específica para Brasil
         */
        validateBrazilianNumber: function (number) {
            // Remover tudo exceto dígitos
            const digits = number.replace(/\D/g, '');

            // Se não começa com 55, não é brasileiro
            if (!digits.startsWith('55')) {
                return true; // Deixar validação padrão
            }

            // Número brasileiro: 55 + DDD (2) + Número (8 ou 9)
            const brazilNumber = digits.substring(2);

            // DDD
            const ddd = brazilNumber.substring(0, 2);
            const validDDDs = [
                '11', '12', '13', '14', '15', '16', '17', '18', '19', // SP
                '21', '22', '24', '27', '28', // RJ, ES
                '31', '32', '33', '34', '35', '37', '38', // MG
                '41', '42', '43', '44', '45', '46', // PR
                '47', '48', '49', // SC
                '51', '53', '54', '55', // RS
                '61', '62', '63', '64', '65', '66', '67', '68', '69', // Centro-Oeste
                '71', '73', '74', '75', '77', '79', // BA, SE
                '81', '82', '83', '84', '85', '86', '87', '88', '89', // NE
                '91', '92', '93', '94', '95', '96', '97', '98', '99'  // Norte
            ];

            if (!validDDDs.includes(ddd)) {
                return false;
            }

            // Número sem DDD
            const phoneNumber = brazilNumber.substring(2);

            // Celular: 9 dígitos começando com 9
            // Fixo: 8 dígitos
            if (phoneNumber.length === 9 && phoneNumber.startsWith('9')) {
                return true; // Celular válido
            }

            if (phoneNumber.length === 8) {
                return true; // Fixo válido
            }

            return false;
        }
    };

    // Inicializar quando DOM estiver pronto
    $(document).ready(function () {
        WECPhone.init();
    });

    // Expor para uso externo
    window.WECPhone = WECPhone;

})(jQuery);
