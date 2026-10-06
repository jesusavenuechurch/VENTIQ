// The ticket page's "Save image": draws the pass into a PNG in the browser.
// Bundled, so the page loads nothing from other sites.
import html2canvas from 'html2canvas';

document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('download-png');
    const pass = document.getElementById('ticket-capture');
    if (!button || !pass) return;

    button.addEventListener('click', async () => {
        const label = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span>Saving…</span>';
        try {
            const canvas = await html2canvas(pass, { scale: 3, useCORS: true, backgroundColor: null, logging: false });
            const link = document.createElement('a');
            link.download = button.dataset.filename || 'ticket.png';
            link.href = canvas.toDataURL('image/png', 1.0);
            link.click();
        } finally {
            button.disabled = false;
            button.innerHTML = label;
        }
    });
});
