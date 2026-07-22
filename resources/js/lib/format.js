/**
 * Helpers de formatage pour le monitoring.
 */

export function formatBytes(bytes, decimals = 1) {
    if (!bytes || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** i).toFixed(decimals)} ${units[i]}`;
}

export function formatRate(bytesPerSecond) {
    return `${formatBytes(bytesPerSecond)}/s`;
}

export function formatUptime(seconds) {
    if (!seconds) return '—';
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (d > 0) return `${d}j ${h}h ${m}m`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

export function formatDateTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR');
}
