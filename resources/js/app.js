import Alpine from 'alpinejs';
import { registerPos } from './pos';

window.Alpine = Alpine;

registerPos(Alpine);
Alpine.start();
