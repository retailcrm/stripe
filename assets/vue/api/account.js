import axios from "axios";
import Routing from "../router/routing";

export default {
    add(slug, account) {
        const formData = new FormData();
        formData.append('account', JSON.stringify(account));

        return axios.post(Routing.generate('stripe_add_account', {slug: slug}), formData);
    },
    refresh(id) {
        return axios.get(Routing.generate('stripe_sync_account', {id: id}));
    },
    deactivate(id) {
        return axios.get(Routing.generate('stripe_deactivate_account', {id: id}));
    },
    edit(account) {
        const formData = new FormData();
        formData.append('account', JSON.stringify(account));

        return axios.post(Routing.generate('stripe_edit_account', {id: account.id}), formData);
    }
};