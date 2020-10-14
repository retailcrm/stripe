import IntegrationAPI from "../api/settings";

const CONNECT = "CONNECT",
    CONNECT_SUCCESS = "CONNECT_SUCCESS",
    CONNECT_ERROR = "CONNECT_ERROR";

export default {
    namespaced: true,
    state: {
        isLoading: false,
        error: null,
        errors: [],
        integration: null
    },
    getters: {
        isLoading(state) {
            return state.isLoading;
        },
        hasError(state) {
            return state.error !== null;
        },
        error(state) {
            return state.error;
        },
        errors(state) {
            return state.errors;
        }
    },
    mutations: {
        [CONNECT](state) {
            state.isLoading = true;
            state.error = null;
            state.errors = [];
        },
        [CONNECT_SUCCESS](state) {
            state.isLoading = false;
            state.error = null;
            state.errors = [];
        },
        [CONNECT_ERROR](state, error) {
            state.isLoading = false;
            state.error = error.errorMsg ? error.errorMsg : 'Неопознанная ошибка';
            state.errors = error.errors ? error.errors : [];
        }
    },
    actions: {
        async connect({ commit }, integration) {
            commit(CONNECT);
            try {
                let response = await IntegrationAPI.connect(integration);
                commit(CONNECT_SUCCESS, response.data);

                return response.data;
            } catch (error) {
                commit(CONNECT_ERROR, error.response.data);

                return null;
            }
        }
    }
};
