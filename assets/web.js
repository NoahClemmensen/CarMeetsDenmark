/**
 * Web platform entrypoint - loads Turbo and Stimulus for SPA-like navigation.
 */
import * as Turbo from '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';
import Highcharts from 'highcharts';
window.Highcharts = Highcharts; // chart controllers use the global via waitForHighcharts()

// Import controllers
import './turbo-actions/redirect.js'; // Custom Turbo Stream actions — self-registering
import './turbo-actions/copy_to_clipboard.js';

// Start Stimulus
const application = Application.start();


// Turbo is enabled automatically on import
Turbo.setProgressBarDelay(100);
