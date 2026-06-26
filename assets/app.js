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

document.addEventListener('turbo:load', () => {
    document.querySelectorAll('.toast').forEach(el => new Toast(el).show());
});
