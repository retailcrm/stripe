import Vue from "vue";
import Vuex from "vuex";
import ConnectModule from "./connect";
import SettingsModule from "./settings";

Vue.use(Vuex);

export default new Vuex.Store({
    modules: {
        connect: ConnectModule,
        settings: SettingsModule,
    }
});
