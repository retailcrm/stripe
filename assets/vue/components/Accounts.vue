<template>
    <div>
        <ui-title>{{ $t('module.settings.title_connected_accounts') }}</ui-title>

        <div
            v-if="hasError"
            style="padding: 10px 0; max-width: 675px;"
        >
            <ui-alert
                type="error"
                :title="error"
                @close="clearError"
            />
        </div>
        <ui-content-box :class="$style['main-box']">
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
                <tr />
            </ui-table>

            <br>
            <form @submit.prevent="addAccount">
                <div :class="$style['add-form']">
                    <div :class="$style['add-form__item']">
                        <ui-text
                            tag="label"
                            accent
                            :class="$style['add-form__label']"
                        >
                            Публичный ключ
                        </ui-text>
                        <ui-input
                            v-model="dataPublicKey"
                            required
                            :class="$style['add-form__area']"
                            :error="errors.publicKey"
                        />
                    </div>
                    <div :class="$style['add-form__item']">
                        <ui-text
                            tag="label"
                            accent
                            :class="$style['add-form__label']"
                        >
                            Секретный ключ
                        </ui-text>
                        <ui-input
                            v-model="dataSecretKey"
                            required
                            :class="$style['add-form__area']"
                            :error="errors.secretKey"
                        />
                    </div>
                    <div :class="$style['add-form__item']">
                        <ui-button
                            size="sm"
                            form-type="submit"
                            :disabled="isLoading || isLoadingAddAccount"
                        >
                            {{ $t('module.settings.add_account') }}
                        </ui-button>
                    </div>
                </div>
            </form>

            <div style="margin-top: 24px;">
                <a
                    :href="paymentTypesUrl"
                    :class="$style['payment-type-link']"
                    target="_blank"
                >{{ $t('module.settings.payment_type_settings') }} <ui-icon name="openInNew" /></a>
            </div>
        </ui-content-box>
    </div>
</template>

<style module lang="less">
    .payment-type-link {
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

    .add-form {
        display: flex;
        margin-top: 16px;

        &__item {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            flex: 1;
            margin-right: 16px;

            &:nth-last-child(1) {
                margin-right: 0;
                flex: none;
            }
        }
        &__label {
            width: 100%;
            margin-bottom: 4px;
        }
        &__area {
            width: 100%;
        }
    }

    .main-box {
        margin-top: 10px;
        min-width: 850px;
        max-width: 1200px;
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
