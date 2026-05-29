/**
 * Worker-Visual Sync bridge for Signal7.
 *
 * Listens for the `signal7:worker-click` window event dispatched by the
 * pipeline canvas (T4.5 React Flow island) and scrolls + briefly highlights
 * the matching asset rows in the AssetsRelationManager table.
 *
 * Event contract: { detail: { asset_ids: string[] } }
 * Producer: T4.5 pipeline canvas (React Flow WorkerNode onClick).
 * Consumer: this bridge.
 * Row selector: td[data-asset-id="<asset_id>"] (placed by AssetsRelationManager).
 *
 * See: .hyper/tasks/T11-content-plan-tree-hyper7-subtask-tree/integration-contract.md
 */

const HIGHLIGHT_DURATION_MS = 2000;
const HIGHLIGHT_BG = 'rgb(254 243 199)'; // amber-100 equivalent

function highlightRow(row) {
    const original = row.style.backgroundColor;
    row.style.transition = 'background-color 0.8s ease-out';
    row.style.backgroundColor = HIGHLIGHT_BG;
    setTimeout(() => {
        row.style.backgroundColor = original;
        setTimeout(() => { row.style.transition = ''; }, 900);
    }, HIGHLIGHT_DURATION_MS);
}

function handleWorkerClick(event) {
    const assetIds = event?.detail?.asset_ids;
    if (!Array.isArray(assetIds) || assetIds.length === 0) return;

    let firstRow = null;

    assetIds.forEach((assetId) => {
        // data-asset-id is on the <td> cell (Filament 5 extraCellAttributes).
        const cells = document.querySelectorAll(`td[data-asset-id="${CSS.escape(assetId)}"]`);
        cells.forEach((cell) => {
            const row = cell.closest('tr');
            if (!row) return;
            if (!firstRow) firstRow = row;
            highlightRow(row);
        });
    });

    if (firstRow) {
        firstRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.addEventListener('signal7:worker-click', handleWorkerClick);
});
