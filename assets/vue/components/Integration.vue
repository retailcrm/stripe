<template>
    <div>
        <ui-title>{{ $t('module.settings.title_connection_setting') }}</ui-title>
        <div
            v-if="hasError"
            style="padding: 10px 0; max-width: 675px;"
        >
            <ui-alert
                type="error"
                icon="error"
                :title="error"
                @close="clearError"
            />
        </div>

        <div
            v-else-if="integrationSuccessUpdate"
            style="padding: 10px 0; max-width: 675px;"
        >
            <ui-alert
                type="success"
                icon="success"
                :title="this.$t('flash.scs_saved')"
                @close="closeAccountSuccessUpdate"
            />
        </div>
        <ui-content-box class="ui-connect-box ui-connect-box_min">
            <div class="ui-connect-form ui-connect-form_full">
                <div class="ui-connect-form__row ui-connect-form__row_inline ui-connect-form__row_edit">
                    <ui-text
                        tag="label"
                        class="ui-connect-form__label"
                    >
                        {{ $t('module.connect.crm_url') }}
                    </ui-text>
                    <div class="ui-connect-form__txt">
                        <ui-text
                            size="md"
                            color="black"
                        >
                            {{ dataCrmUrl }}
                        </ui-text>
                    </div>
                </div>
                <div class="ui-connect-form__row ui-connect-form__row_inline ui-connect-form__row_edit">
                    <ui-text
                        tag="label"
                        class="ui-connect-form__label"
                    >
                        {{ $t('module.connect.api_key') }}
                    </ui-text>
                    <div
                        v-if="!dataEditApiKey.isEdit"
                        class="ui-connect-form__txt"
                    >
                        <ui-text
                            size="md"
                            color="black"
                        >
                            {{ dataCrmApiKey }}
                        </ui-text>
                        <ui-icon
                            name="edit"
                            btn
                            class="ui-connect-form__btn"
                            @click.native="dataEditApiKey.isEdit = true"
                        />
                    </div>
                    <div
                        v-else
                        class="ui-connect-form-edit"
                    >
                        <div class="ui-connect-form-edit__area">
                            <ui-input
                                v-model="dataEditApiKey.value"
                                min
                                required
                                :error="errors.crmApiKey"
                                :placeholder="dataCrmApiKey"
                            />
                        </div>
                        <div class="ui-connect-form-edit__action">
                            <ui-action-btn
                                type="success"
                                :class="{ 'disable-events' : isLoading }"
                                @click="updateIntegration"
                            />
                            <ui-action-btn
                                type="cancel"
                                :class="{ 'disable-events' : isLoading }"
                                @click="updateIntegrationCancel"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </ui-content-box>
    </div>
</template>

<script>
    export default {
        name: "Integration",
        props: {
            crmUrl: {
                type: String,
                required: true
            },
            id: {
                type: String,
                required: true
            },
            crmApiKey: {
                type: String,
                required: true
            },
            isEnabled: {
                type: Boolean,
                required: true
            }
        },
        data() {
            return {
                dataCrmUrl: this.crmUrl,
                dataCrmApiKey: this.crmApiKey,
                dataId: this.id,
                dataIsEnabled: this.isEnabled,
                dataEditApiKey: {
                    value: "",
                    isEdit: false
                }
            };
        },
        computed: {
            isLoading() {
                return this.$store.getters["settings/integrationLoading"];
            },
            hasError() {
                return this.$store.getters["settings/hasIntegrationError"];
            },
            error() {
                return this.$store.getters["settings/integrationError"];
            },
            errors() {
                return this.$store.getters["settings/integrationErrors"];
            },
            hasIntegration() {
                return this.$store.getters["settings/hasIntegration"];
            },
            integration() {
                return this.$store.getters["settings/integration"];
            },
            integrationSuccessUpdate() {
                return this.$store.getters["settings/integrationSuccessUpdate"];
            }
        },
        methods: {
            updateIntegrationCancel() {
                this.dataEditApiKey.value = "";
                this.dataEditApiKey.isEdit = false;
            },
            async updateIntegration() {
                if (!this.dataEditApiKey.value) {
                    this.updateIntegrationCancel();
                    return;
                }
                let payload = {id: this.$route.params.id, apiKey: this.dataEditApiKey.value};
                const data = await this.$store.dispatch("settings/updateIntegration", payload);
                if (data !== null && data.integration !== null) {
                    this.dataCrmApiKey = this.dataEditApiKey.value;
                    this.dataEditApiKey.isEdit = false;
                }
            },
            clearError() {
                this.$store.dispatch("settings/clearError", 'integration');
            },
            closeAccountSuccessUpdate() {
                this.$store.dispatch("settings/hideAlert", 'integration_success_update');
            }
        }
    };
</script>
