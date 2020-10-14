import '../css/components.less';
import 'vue-ui-kit/dist/ui-kit.css';
import UiLibrary from 'vue-ui-kit/dist/ui-kit.common';

import Vue from "vue";
import VueI18n from 'vue-i18n'
import App from "./App";
import router from "./router";
import store from "./store";

Vue.use(UiLibrary);
Vue.use(VueI18n);

const messages = window.app_translations;
const i18n = new VueI18n({
    locale: window.app_locale,
    messages
});

new Vue({
    i18n,
    components: { App },
    template: "<App/>",
    router,
    store
}).$mount("#app");
