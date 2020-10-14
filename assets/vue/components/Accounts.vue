<template>
    <div>
        <ui-title>{{ $t('module.settings.title_connected_accounts') }}</ui-title>

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
        <ui-content-box style="margin-top: 10px">
            <ui-icon
                v-if="isLoading"
                style="display:inline-block; max-width: 50px;max-height: 20px;padding-top: 15px"
                name="loading"
            />

            <ui-text
                v-else-if="!accounts.length"
                color="gray"
                style="padding-top: 15px;"
            >
                {{ $t('module.settings.no_accounts') }}
            </ui-text>

            <ui-table v-if="accounts.length">
                <thead>
                    <tr>
                        <th>{{ $t('module.settings.account') }}</th>
                        <th>{{ $t('module.settings.account_name') }}</th>
                        <th>{{ $t('module.settings.test') }}</th>
                        <th>{{ $t('module.settings.approve_manually') }}</th>
                        <th />
                    </tr>
                </thead>

                <account
                    v-for="account in accounts"
                    :id="account.id"
                    :key="account.id"
                    :account-id="account.accountId"
                    :is-activated="account.isActivated"
                    :name="account.name"
                    :test="account.test"
                    :approve-manually="account.approveManually"
                />
                <tr>
                    <td
                        colspan="6"
                        style="text-align: left; padding-bottom: 0;"
                    >
                        <a
                            :href="paymentTypesUrl"
                            :class="$style['payment-type-link']"
                            target="_blank"
                        >{{ $t('module.settings.payment_type_settings') }} <ui-icon name="openInNew" /></a>
                    </td>
                </tr>
            </ui-table>

            <br>
            <form @submit.prevent="addAccount">
                <div>
                    <ui-input
                        v-model="dataPublicKey"
                        required
                        :error="errors.publicKey"
                    />
                </div>
                <div>
                    <ui-input
                        v-model="dataSecretKey"
                        required
                        :error="errors.secretKey"
                    />
                </div>
                <div>
                    <ui-button
                        style="margin-top: 15px;"
                        size="sm"
                        :disabled="isLoading || isLoadingAddAccount"
                    >
                        {{ $t('module.settings.add_account') }}
                        <ui-icon name="addCircleOutlined" />
                    </ui-button>
                </div>
            </form>
        </ui-content-box>
    </div>
</template>

<style module lang="css">
    .payment-type-link{
        display: inline-block;
        vertical-align: top;
        color: #0384fc;
        line-height: 24px;
        font-size: 16px;
        text-decoration: none;
        letter-spacing: .1px;
    }
    .payment-type-link svg {
        display: inline-block;
        vertical-align: middle;
        width: 24px;
        height: 24px;
        fill: #0384fc;
    }
</style>

<script>
    import Account from "../components/Account";

    export default {
        name: "Accounts",
        components: {
            Account
        },
        props: {
            accounts: {
                type: Array,
                default() {
                    return []
                }
            },
            paymentTypesUrl: {
                type: String,
                default: '#'
            },
            isLoading: {
                type: Boolean,
                default: false
            }
        },
        data() {
            return {
                dataPublicKey: '',
                dataSecretKey: '',
            };
        },
        computed: {
            hasError() {
                return this.$store.getters["settings/accountHasError"];
            },
            error() {
                return this.$store.getters["settings/accountError"];
            },
            errors() {
                return this.$store.getters["settings/accountErrors"];
            },
            isLoadingAddAccount() {
                return this.$store.getters["settings/isLoadingAddAccount"];
            }
        },
        methods: {
            async addAccount() {
                let data = {
                    slug: this.$route.params.id,
                    account: {
                        publicKey: this.dataPublicKey,
                        secretKey: this.dataSecretKey
                    },
                };

                const result = await this.$store.dispatch("settings/addAccount", data);
                if (result !== null) {
                    window.location.reload(true);
                }
            },
            clearError() {
                this.$store.dispatch("settings/clearError", 'accounts');
            }
        }
    };
</script>
