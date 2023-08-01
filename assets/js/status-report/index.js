/* global ClipboardJS */

/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { reports } from './config';
import Reports from './components/reports';

/**
 * Status report copy button.
 *
 * @returns {void}
 */
const init = () => {
	const report = document.getElementById('ep-status-reports');
	const clipboard = new ClipboardJS('#ep-copy-report');
	const root = createRoot(report);

	/**
	 * Handle successful copy.
	 *
	 * @param {Event} event Copy event.
	 * @returns {void}
	 */
	const onSuccess = (event) => {
		event.trigger.nextElementSibling.style.display = 'initial';

		setTimeout(() => {
			event.trigger.nextElementSibling.style.display = 'none';
		}, 3000);

		event.clearSelection();
	};

	/**
	 * Bind copy button events.
	 */
	clipboard.on('success', onSuccess);

	/**
	 * Render reports.
	 */
	root.render(<Reports reports={reports} />);
};

domReady(init);
