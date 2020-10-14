import axios from "axios";
import Routing from "../router/routing";

export default {
    getData(id) {
        return axios.get(Routing.generate('stripe_get_settings', {slug: id}));
    },
    connect(integration) {
        const formData = new FormData();
        formData.append('integration', JSON.stringify(integration));

        return axios.post(Routing.generate('stripe_connect'), formData);
    }
};