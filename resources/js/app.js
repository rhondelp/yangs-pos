import Alpine from 'alpinejs';
import { registerInventory } from './inventory';
import { registerPos } from './pos';

window.Alpine = Alpine;

registerPos(Alpine);
registerInventory(Alpine);
Alpine.start();
