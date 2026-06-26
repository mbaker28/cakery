/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import { Toast } from 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@hotwired/turbo';

function showToast(el) {
    if (!Toast.getInstance(el)) new Toast(el).show();
}

document.addEventListener('turbo:load', () => {
    document.querySelectorAll('.toast').forEach(showToast);

    const container = document.getElementById('toast-container');
    if (container) {
        new MutationObserver(mutations => {
            mutations.forEach(m => m.addedNodes.forEach(node => {
                if (node.nodeType === 1 && node.classList.contains('toast')) showToast(node);
            }));
        }).observe(container, { childList: true });
    }
});
