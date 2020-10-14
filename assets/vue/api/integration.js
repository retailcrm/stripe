import axios from "axios";
import Routing from "../router/routing";

export default {
    updateIntegration(id, apiKey) {
        const formData = new FormData();
        formData.append('apiKey', apiKey);

        return axios.post(Routing.generate('stripe_edit_settings', {slug: id}), formData);
    }
};