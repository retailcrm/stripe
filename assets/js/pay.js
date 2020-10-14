import 'picnic';
import 'font-awesome/less/font-awesome.less';
import '../css/custom.less';

import $ from 'jquery';
import {loadStripe} from '@stripe/stripe-js';
//import Loader from "./loader";

//$('[data-behaviour="loader"]').each(function () {
//    new Loader($(this)).load();
//});

const publicKey = $('.loader').data('key');
const sessionId = $('.loader').data('session-id');

if (publicKey && sessionId) {
    redirectToStripe(publicKey, sessionId);
}

async function redirectToStripe(publicKey, sessionId) {

    const stripe = await loadStripe(publicKey);
    stripe.redirectToCheckout({
        sessionId: sessionId
    })
}