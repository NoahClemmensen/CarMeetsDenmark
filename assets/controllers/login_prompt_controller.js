import { Controller } from '@hotwired/stimulus';
import { t } from '../utilities/i18n.js';

/**
 * Guest "log in to continue" prompt.
 *
 * Registered once on <body> so any interaction control rendered for a guest can
 * trigger it via `data-action="login-prompt#open"`. Instead of firing the
 * (server-rejected) request, it opens the shared app modal with Log in / Sign up
 * actions. The server still blocks the underlying POST, so this is purely a UX
 * shortcut, not the security boundary.
 */
export default class extends Controller {
    static values = {
        loginUrl: String,
        registerUrl: String,
    };

    open(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const modalElement = document.getElementById('app-modal');
        const modal = modalElement
            ? this.application.getControllerForElementAndIdentifier(modalElement, 'modal')
            : null;

        // No modal on the page (or controller not ready): fall back to the login page.
        if (!modal) {
            window.location.href = this.loginUrlValue;
            return;
        }

        const message = event?.currentTarget?.dataset?.loginPromptMessage
            ?? t('login_prompt.default_message');

        if (modal.hasHeaderTarget) {
            modal.headerTarget.innerHTML =
                '<h2 id="modal-title" class="modal-title">' + t('login_prompt.title') + '</h2>' +
                '<button type="button" class="modal-close" data-action="click->modal#hide" aria-label="' + t('common.close') + '">&times;</button>';
        }
        if (modal.hasBodyTarget) {
            modal.bodyTarget.innerHTML =
                '<div class="modal-body"><p class="text-sm text-secondary-text m-0">' + message + '</p></div>';
        }
        if (modal.hasFooterTarget) {
            modal.footerTarget.innerHTML =
                '<div class="modal-footer">' +
                '<a class="btn-secondary" href="' + this.registerUrlValue + '">' + t('auth.sign_up') + '</a>' +
                '<a class="btn-cta" href="' + this.loginUrlValue + '">' + t('auth.log_in') + '</a>' +
                '</div>';
        }

        modal.show();
    }
}
