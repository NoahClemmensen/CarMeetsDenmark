/**
 * Web platform entrypoint - loads Turbo and Stimulus for SPA-like navigation.
 */
import * as Turbo from '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';
import Highcharts from 'highcharts';
window.Highcharts = Highcharts; // chart controllers use the global via waitForHighcharts()

// Import controllers
import FilterFormController from './controllers/filter_form_controller.js';
import PaginationController from './controllers/pagination_controller.js';
import ToastController from './controllers/toast_controller.js';
import TurboErrorHandlerController from './controllers/turbo_error_handler_controller.js';
import ModalController from "./controllers/modal_controller.js";
import AccordionController from "./controllers/accordion_controller.js";
import DropdownController from "./controllers/dropdown_controller.js";
import ModalTriggerController from "./controllers/modal_trigger_controller.js";
import QrModalController from "./controllers/qr_code_controller.js";
import ShareController from "./controllers/share_controller.js";
import SegmentedControlController from "./controllers/segmented_control_controller.js";
import SubmitOnceController from "./controllers/submit_once_controller.js";
import UserSetupController from "./controllers/user_setup_controller.js";
import EventBannerEditorController from "./controllers/event_banner_editor_controller.js";
import UserAvatarEditorController from "./controllers/user_avatar_editor_controller.js";
import PostComposerController from "./controllers/post_composer_controller.js";
import LightboxController from "./controllers/lightbox_controller.js";
import IgEmbedController from "./controllers/ig_embed_controller.js";
import './turbo-actions/redirect.js'; // Custom Turbo Stream actions — self-registering
import './turbo-actions/copy_to_clipboard.js';
import './turbo-actions/modal_hide.js';

// Start Stimulus
const application = Application.start();
application.register('filter-form', FilterFormController);
application.register('pagination', PaginationController);
application.register('toast', ToastController);
application.register('turbo-error-handler', TurboErrorHandlerController);
application.register('modal', ModalController);
application.register('accordion', AccordionController);
application.register('dropdown', DropdownController);
application.register('modal-trigger', ModalTriggerController);
application.register('qr-code', QrModalController);
application.register('share', ShareController);
application.register('segmented-control', SegmentedControlController);
application.register('submit-once', SubmitOnceController);
application.register('user-setup', UserSetupController);
application.register('event-banner-editor', EventBannerEditorController);
application.register('user-avatar-editor', UserAvatarEditorController);
application.register('post-composer', PostComposerController);
application.register('lightbox', LightboxController);
application.register('ig-embed', IgEmbedController);

// Turbo is enabled automatically on import
Turbo.setProgressBarDelay(100);
