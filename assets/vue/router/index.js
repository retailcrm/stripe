import Vue from "vue";
import VueRouter from "vue-router";
import Settings from "../views/Settings";
import Connect from "../views/Connect";
import PageNotFound from "../views/PageNotFound";

Vue.use(VueRouter);

export default new VueRouter({
    mode: "history",
    routes: [
        { path: "/", component: Connect },
        { path: "/settings/:id", component: Settings },
        { path: "*", component: PageNotFound },
    ],
});