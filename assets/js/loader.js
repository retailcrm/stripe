import $ from 'jquery';

export default class Loader {
    constructor(element) {
        this.element = element;
        this.downloading = element.find('.downloading');
        this.error = element.find('.error');
    }

    load() {
        const self = this;
        $.ajax({
            method: "POST",
            url: this.element.data('slug'),
        })
        .done(function (data) {
            if (data.success) {
                window.location.href = data.url;
            } else {
                self.showError();
            }
        })
        .fail(function () {
            self.showError();
        })
    }

    showError() {
        this.downloading.addClass('hidden');
        this.error.removeClass('hidden');
    }
}
