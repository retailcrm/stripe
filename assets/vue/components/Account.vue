<template>
    <tr :class="!dataIsActivated ? $style['removed-account'] : ''">
        <td>
            <ui-text
                :color="!dataIsActivated ? 'gray' : 'black'"
                :class="$style['td-text']"
            >
                {{ dataAccountId }}
            </ui-text>
        </td>
        <td style="width: 290px;">
            <ui-text
                :color="!dataIsActivated ? 'gray' : 'black'"
                :class="$style['td-text']"
            >
                <div
                    v-if="!dataEditName.isEdit"
                    class="ui-connect-form__txt"
                >
                    <ui-text
                        tag="label"
                        :class="$style['account-name']"
                    >
                        {{ dataName }}
                    </ui-text>
                    <ui-icon
                        v-if="dataIsActivated"
                        style="max-width: 16px"
                        name="edit"
                        btn
                        :disabled="isLoading"
                        :class="['ui-connect-form__btn', { 'disable-events' : isLoading }]"
                        @click.native="dataEditName.isEdit = true"
                    />
                </div>
                <div
                    v-else
                    class="ui-connect-form-edit"
                >
                    <div class="ui-connect-form-edit__area">
                        <ui-input
                            v-model="dataEditName.value"
                            style="max-width: 270px"
                            min
                            :error="errors.name"
                        />
                    </div>
                    <div class="ui-connect-form-edit__action">
                        <ui-action-btn
                            :disabled="isLoading"
                            :class="{ 'disable-events' : isLoading }"
                            type="success"
                            @click="editAccount"
                        />
                        <ui-action-btn
                            :class="{ 'disable-events' : isLoading }"
                            type="cancel"
                            @click="editNameCancel"
                        />
                    </div>
                </div>
            </ui-text>
        </td>
        <td>
            <ui-icon
                :class="$style['table-icon']"
                :name="dataTest ? 'done' : 'clear'"
            />
        </td>
        <td>
            <ui-switch
                v-model="dataApproveManually"
                :disabled="!dataIsActivated || isLoading"
                @change="editAccount"
            />
        </td>
        <td style="width: 195px">
            <div v-if="dataIsActivated">
                <ui-info-btn
                    :disabled="isLoading"
                    :state="isLoading ? 'loading' : 'default'"
                    style="display: inline-block;"
                    :class="{ 'disable-events' : isLoading }"
                    @click="deactivateAccount()"
                >
                    {{ $t('button.account_delete') }}
                </ui-info-btn>
                <ui-button
                    size="sm"
                    type="secondary"
                    :disabled="isLoading"
                    style="display: inline-block;margin-left: 10px"
                    @click="refreshAccount()"
                >
                    <ui-icon name="actionsRefresh20" /> {{ $t('button.account_refresh') }}
                </ui-button>
            </div>
        </td>
    </tr>
</template>

<style module lang="css">
    .removed-account{
        background: #f4f6f8;
    }
    .table-icon{
        display:inline-block;
        max-height: 30px;
        fill: #000;
    }
    .removed-account .table-icon{
        fill: #dfe3e9;
    }
    .removed-account .td-text{
        text-decoration: line-through;
    }
    .account-name{
        display:inline-block;
        max-width: 220px;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
        color: #1e2248;
    }
    .removed-account .td-text .account-name{
        color: #919eab;
    }
</style>

<script>
    export default {
        name: "Account",
        props: {
            name: {
                type: String,
                required: true
            },
            id: {
                type: String,
                required: true
            },
            test: {
                type: Boolean,
                required: true
            },
            approveManually: {
                type: Boolean,
                required: true
            },
            isActivated: {
                type: Boolean,
                required: true
            },
            accountId: {
                type: String,
                required: true
            }
        },
        data() {
            return {
                dataId: this.id,
                dataAccountId: this.accountId,
                dataTest: this.test,
                dataName: this.name,
                dataApproveManually: this.approveManually,
                dataIsActivated: this.isActivated,
                dataEditName: {
                    value: this.name,
                    isEdit: false
                },
                errors: []
            };
        },
        computed: {
            isLoading() {
                return this.$store.getters["settings/accountIsLoading"](this.dataId);
            },
        },
        watch: {
            'dataEditName.isEdit' () {
                this.errors = [];
            }
        },
        methods: {
            async refreshAccount() {
                const data = await this.$store.dispatch("settings/refreshAccount", this.dataId);
                this.updateAccount(data);
            },
            async deactivateAccount() {
                if (confirm(this.$t('module.settings.sure'))) {
                    const data = await this.$store.dispatch("settings/deactivateAccount", this.dataId);
                    this.updateAccount(data);
                }
            },
            async editAccount() {
                this.errors = [];
                let account = {
                    name: this.dataEditName.value,
                    id: this.dataId,
                    approveManually: this.dataApproveManually,
                };
                try {
                    const data = await this.$store.dispatch("settings/editAccount", account);
                    if (this.updateAccount(data)) {
                        this.dataEditName.isEdit = false;
                    }
                } catch (error) {
                    const errors = error.response.data.errors;
                    this.errors = errors || [];
                }
            },
            editNameCancel() {
                this.dataEditName.value = this.dataName;
                this.dataEditName.isEdit = false;
            },
            updateAccount(data) {
                if (data !== null && data.account !== null) {
                    let account = data.account;
                    this.dataName = account.name;
                    this.dataTest = account.test;
                    this.dataApproveManually = account.approveManually;
                    this.dataIsActivated = account.isActivated;
                    this.dataAccountId = account.accountId;

                    return true;
                }

                return false;
            }
        }
    };
</script>
