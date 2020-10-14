import SettingsAPI from "../api/settings";
import IntegrationAPI from "../api/integration";
import AccountAPI from "../api/account";

const FETCHING_SETTINGS = "FETCHING_SETTINGS",
    FETCHING_SETTINGS_SUCCESS = "FETCHING_SETTINGS_SUCCESS",
    FETCHING_SETTINGS_ERROR = "FETCHING_SETTINGS_ERROR",
    UPDATE_INTEGRATION = "UPDATE_INTEGRATION",
    UPDATE_INTEGRATION_SUCCESS = "UPDATE_INTEGRATION_SUCCESS",
    UPDATE_INTEGRATION_ERROR = "UPDATE_INTEGRATION_ERROR",
    ADD_ACCOUNT = "ADD_ACCOUNT",
    ADD_ACCOUNT_SUCCESS = "ADD_ACCOUNT_SUCCESS",
    ADD_ACCOUNT_ERROR = "ADD_ACCOUNT_ERROR",
    CHANGE_ACCOUNT = "EDIT_ACCOUNT",
    CHANGE_ACCOUNT_SUCCESS = "EDIT_ACCOUNT_SUCCESS",
    CHANGE_ACCOUNT_ERROR = "EDIT_ACCOUNT_ERROR",
    CLEAR_ERROR = "CLEAR_ERROR";

function removeItem (items, id) {
    if (items) {
        let index = items.indexOf(id);
        if (index !== -1) items.splice(index, 1);
    }
}

export default {
    namespaced: true,
    state: {
        isLoading: false,
        error: null,
        errors: [],
        integration: null,
        integrationData: {
            isLoading: false,
            successUpdate: false,
            error: null,
            errors: [],
        },
        accounts: [],
        moduleCode: '',
        accountData: {
            isLoading: [],
            isLoadingAddAccount: false,
            error: null, // Общая ошибка для одного из аккаунтов
            errors: [], // Ошибки для каждого из аккаунтов
        }
    },
    getters: {
        isLoading(state) {
            return state.isLoading;
        },
        hasError(state) {
            return state.error !== null || window.flashMsg;
        },
        error(state) {
            return state.error || window.flashMsg;
        },
        errors(state) {
            return state.errors;
        },
        hasAccounts(state) {
            return state.accounts.length > 0;
        },
        accounts(state) {
            return state.accounts;
        },
        hasIntegration(state) {
            return state.integration !== null;
        },
        integration(state) {
            return state.integration;
        },
        moduleCode(state) {
            return state.moduleCode;
        },
        //Integration getters
        integrationLoading(state) {
            return state.integrationData.isLoading;
        },
        hasIntegrationError(state) {
            return state.integrationData.error !== null;
        },
        integrationError(state) {
            return state.integrationData.error;
        },
        integrationErrors(state) {
            return state.integrationData.errors;
        },
        integrationSuccessUpdate(state) {
            return state.integrationData.successUpdate;
        },
        // Account getters
        accountIsLoading: (state) => (id) => {
            return state.accountData.isLoading && state.accountData.isLoading.indexOf(id) !== -1;
        },
        isLoadingAddAccount(state) {
            return state.accountData.isLoadingAddAccount;
        },
        accountHasError(state) {
            return state.accountData.error !== null;
        },
        accountError(state) {
            return state.accountData.error;
        },
        accountErrors: (state) => (id) => {
            return state.accountData.errors[id] ? state.accountData.errors[id] : [];
        },
    },
    mutations: {
        [FETCHING_SETTINGS](state) {
            state.isLoading = true;
            state.error = null;
            state.errors = [];
            state.accounts = [];
        },
        [FETCHING_SETTINGS_SUCCESS](state, data) {
            state.isLoading = false;
            state.error = null;
            state.errors = [];
            state.accounts = data.accounts;
            state.integration = data.integration;
            state.moduleCode = data.moduleCode;
        },
        [FETCHING_SETTINGS_ERROR](state, error) {
            state.isLoading = false;
            state.error = error.errorMsg ? error.errorMsg : 'Неопознанная ошибка';
            state.errors = error.errors ? error.errors : [];
            state.accounts = [];
            state.integration = null;
        },
        [UPDATE_INTEGRATION](state) {
            state.integrationData.isLoading = true;
            state.integrationData.error = null;
            state.integrationData.errors = [];
            state.integrationData.successUpdate = false;
        },
        [UPDATE_INTEGRATION_SUCCESS](state, data) {
            state.integrationData.isLoading = false;
            state.integrationData.error = null;
            state.integrationData.errors = [];
            state.integration = data.integration;
            state.integrationData.successUpdate = true;
        },
        [UPDATE_INTEGRATION_ERROR](state, error) {
            state.integrationData.isLoading = false;
            state.integrationData.error = error.errorMsg ? error.errorMsg : 'Неопознанная ошибка';
            state.integrationData.errors = error.errors ? error.errors : [];
            state.integrationData.successUpdate = false;
        },
        [ADD_ACCOUNT](state) {
            state.accountData.isLoadingAddAccount = true;
            state.accountData.error = null;
            state.accountData.errors = [];
        },
        [ADD_ACCOUNT_SUCCESS](state) {
            state.accountData.isLoadingAddAccount = false;
            state.accountData.error = null;
        },
        [ADD_ACCOUNT_ERROR](state, error) {
            state.accountData.isLoadingAddAccount = false;
            state.accountData.error = error.errorMsg ? error.errorMsg : 'Неопознанная ошибка';
        },
        [CHANGE_ACCOUNT](state, id) {
            state.accountData.isLoading.push(id);
            state.accountData.error = null;
        },
        [CHANGE_ACCOUNT_SUCCESS](state, data) {
            removeItem(state.accountData.isLoading, data.account.id);
            state.accountData.error = null;
            state.accounts.find(function (item, index) {
                if (item.id === data.account.id) {
                    state.accounts[index] = data.account;

                    return true;
                }
            });
            delete state.accountData.errors[data.account.id];
        },
        [CHANGE_ACCOUNT_ERROR](state, data) {
            let error = data.error;
            removeItem(state.accountData.isLoading, data.id);
            state.accountData.error = error.errorMsg ? error.errorMsg : 'Неопознанная ошибка';
        },
        [CLEAR_ERROR](state, type) {
            switch (type) {
                case 'global':
                    state.error = null;
                    break;
                case 'integration':
                    state.integrationData.error = null;
                    break;
                case 'accounts':
                    state.accountData.error = null;
                    break;
            }
        }
    },
    actions: {
        async getData({commit}, id) {
            commit(FETCHING_SETTINGS);
            try {
                let response = await SettingsAPI.getData(id);
                commit(FETCHING_SETTINGS_SUCCESS, response.data);

                return response.data;
            } catch (error) {
                commit(FETCHING_SETTINGS_ERROR, error.response.data);

                return null;
            }
        },
        async updateIntegration({ commit }, payload) {
            commit(UPDATE_INTEGRATION);
            try {
                let response = await IntegrationAPI.updateIntegration(payload.id, payload.apiKey);
                commit(UPDATE_INTEGRATION_SUCCESS, response.data);

                return response.data;
            } catch (error) {
                commit(UPDATE_INTEGRATION_ERROR, error.response.data);

                return null;
            }
        },
        async addAccount({ commit }, data) {
            commit(ADD_ACCOUNT);

            let slug = data.slug;
            let account = data.account;

            try {
                let response = await AccountAPI.add(slug, account);
                commit(ADD_ACCOUNT_SUCCESS);
                return response.data;
            } catch (error) {
                commit(ADD_ACCOUNT_ERROR, error.response.data);
                return null;
            }
        },
        async refreshAccount({ commit }, id) {
            commit(CHANGE_ACCOUNT, id);
            try {
                let response = await AccountAPI.refresh(id);
                commit(CHANGE_ACCOUNT_SUCCESS, response.data);

                return response.data;
            } catch (error) {
                commit(CHANGE_ACCOUNT_ERROR, {error: error.response.data, id: id});

                return null;
            }
        },
        async deactivateAccount({ commit }, id) {
            commit(CHANGE_ACCOUNT, id);
            try {
                let response = await AccountAPI.deactivate(id);
                commit(CHANGE_ACCOUNT_SUCCESS, response.data);

                return response.data;
            } catch (error) {
                commit(CHANGE_ACCOUNT_ERROR, {error: error.response.data, id: id});

                return null;
            }
        },
        async editAccount({ commit }, account) {
            commit(CHANGE_ACCOUNT, account.id);
            try {
                let response = await AccountAPI.edit(account);
                commit(CHANGE_ACCOUNT_SUCCESS, response.data);

                return response.data;
            } catch (error) {
                commit(CHANGE_ACCOUNT_ERROR, {error: error.response.data, id: account.id});

                throw error;
            }
        },
        async clearError({ commit }, type) {
            commit(CLEAR_ERROR, type);
        },
    }
};
