<template>
    <ui-integration-layout
        doc-link="https://docs.retailcrm.pro/Users/Integration"
        :integration="this.$t('base.integrations')"
        :integration-value="this.$t('base.curIntegration')"
        :integration-link="urls.crmIntegrations"
        :integration-value-link="urls.crmModule"
        :main-link="urls.main"
    >
        <template
            v-if="hasIntegration"
            v-slot:headerContent
        >
            <div
                v-if="integration.isEnabled"
                class="ui-header-status ui-header-status_success"
            >
                {{ $t('module.status.active') }}
            </div>
            <div
                v-else
                class="ui-header-status ui-header-status_error"
            >
                {{ $t('module.status.inactive') }}
            </div>
        </template>

        <template v-slot:asideContent>
            <div>
                <ui-menu-btn
                    icon="linkIcon"
                    :active="menu.settings.isActive"
                    @click="switchMenu('settings')"
                >
                    {{ menu.settings.title }}
                </ui-menu-btn>
                <ui-menu-btn
                    icon="shopOutlined"
                    :active="menu.accounts.isActive"
                    @click="switchMenu('accounts')"
                >
                    {{ menu.accounts.title }}
                </ui-menu-btn>
            </div>
        </template>
        <template v-slot:mainContent>
            <div>
                <div
                    v-if="hasError"
                    style="padding-bottom: 20px; max-width: 675px;"
                >
                    <ui-alert
                        type="error"
                        icon="error"
                        :title="error"
                        @close="clearError"
                    />
                </div>
                <ui-icon
                    v-if="isLoading"
                    style="display:inline-block; max-width: 50px;max-height: 20px;padding-top: 15px"
                    name="loading"
                />
                <integration
                    v-if="hasIntegration && menu.settings.isActive"
                    :id="integration.id"
                    :crm-api-key="integration.crmApiKey"
                    :crm-url="integration.crmUrl"
                    :is-enabled="integration.isEnabled"
                />

                <accounts
                    v-if="hasIntegration && menu.accounts.isActive"
                    :accounts="accounts"
                    :is-loading="isLoading"
                    :payment-types-url="urls.paymentTypes"
                />
            </div>
        </template>
    </ui-integration-layout>
</template>

<script>
    import Integration from "../components/Integration";
    import Accounts from "../components/Accounts";

    export default {
        name: "Settings",
        components: {
            Accounts,
            Integration
        },
        data() {
            return {
                menu: {
                    settings: {
                        title: this.$t('module.settings.btn_connection_setting'),
                        isActive: true
                    },
                    accounts: {
                        title: this.$t('module.settings.btn_connected_accounts'),
                        isActive: false
                    }
                },
            }
        },
        computed: {
            isLoading() {
                return this.$store.getters["settings/isLoading"];
            },
            hasError() {
                return this.$store.getters["settings/hasError"];
            },
            error() {
                return this.$store.getters["settings/error"];
            },
            errors() {
                return this.$store.getters["settings/errors"];
            },
            hasAccounts() {
                return this.$store.getters["settings/hasAccounts"];
            },
            accounts() {
                return this.$store.getters["settings/accounts"];
            },
            hasIntegration() {
                return this.$store.getters["settings/hasIntegration"];
            },
            integration() {
                return this.$store.getters["settings/integration"];
            },
            moduleCode() {
                return this.$store.getters["settings/moduleCode"];
            },
            urls() {
                let domain = this.hasIntegration ? this.integration.crmUrl : '#';

                return {
                    main: domain,
                    paymentTypes: domain + '/admin/payment-types',
                    crmIntegrations: domain + '/admin/integration/list',
                    crmModule: domain + '/admin/integration/' + this.moduleCode + '/edit'
                };
            }
        },
        created() {
            this.$store.dispatch("settings/getData", this.$route.params.id);
            if (location.hash && location.hash === '#accounts') {
                this.menu.settings.isActive = false;
                this.menu.accounts.isActive = true;
            }
        },
        methods: {
            switchMenu(active) { //TODO: странный способ, мб стоит переписать.
                if (active === 'settings') {
                    this.menu.settings.isActive = true;
                    this.menu.accounts.isActive = false;
                    location.hash = '';
                } else {
                    this.menu.settings.isActive = false;
                    this.menu.accounts.isActive = true;
                    location.hash = 'accounts';
                }
            },
            clearError() {
                this.$store.dispatch("settings/clearError", 'global');
            }
        }
    };
</script>