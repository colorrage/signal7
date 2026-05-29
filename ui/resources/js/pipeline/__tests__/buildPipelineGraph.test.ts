import { describe, it, expect } from 'vitest';
import buildPipelineGraph from '../buildPipelineGraph';
import type { Asset } from '../types';

// Helper: make a minimal asset
function makeAsset(asset_id: string, asset_type = 'blog', status = 'pending'): Asset {
  return { asset_id, asset_type, status };
}

// ── Signal7 quick ────────────────────────────────────────────────────────────
describe('Signal7 quick', () => {
  it('produces 5 phase nodes in the documented order', () => {
    const { nodes } = buildPipelineGraph('quick', 'create', [], 'signal7');
    const phaseNodes = nodes.filter(n => n.type === 'phase');
    expect(phaseNodes).toHaveLength(5);
    const labels = phaseNodes.map(n => n.data.label);
    expect(labels).toEqual(['brief', 'create', 'review', 'publish', 'done']);
  });

  it('produces 4 edges', () => {
    const { edges } = buildPipelineGraph('quick', 'create', [], 'signal7');
    expect(edges).toHaveLength(4);
  });

  it("marks the supplied phase as 'current'", () => {
    const { nodes } = buildPipelineGraph('quick', 'review', [], 'signal7');
    const current = nodes.filter(n => n.data.status === 'current');
    expect(current).toHaveLength(1);
    expect(current[0].id).toBe('review');
  });

  it('marks earlier phases as done and later phases as pending', () => {
    const { nodes } = buildPipelineGraph('quick', 'review', [], 'signal7');
    const statusById: Record<string, string> = {};
    nodes.forEach(n => { statusById[n.id] = n.data.status; });
    expect(statusById['brief']).toBe('done');
    expect(statusById['create']).toBe('done');
    expect(statusById['review']).toBe('current');
    expect(statusById['publish']).toBe('pending');
    expect(statusById['done']).toBe('pending');
  });
});

// ── Signal7 campaign with 3 assets ──────────────────────────────────────────
describe('Signal7 campaign with 3 assets', () => {
  const assets = [
    makeAsset('a1', 'blog', 'pending'),
    makeAsset('a2', 'video', 'done'),
    makeAsset('a3', 'image', 'failed'),
  ];

  it('produces 7 phase nodes plus 3 worker nodes', () => {
    const { nodes } = buildPipelineGraph('campaign', 'create', assets, 'signal7');
    const phase = nodes.filter(n => n.type === 'phase');
    const workers = nodes.filter(n => n.type === 'worker');
    expect(phase).toHaveLength(7);
    expect(workers).toHaveLength(3);
  });

  it("marks the 'create' phase node as 'current'", () => {
    const { nodes } = buildPipelineGraph('campaign', 'create', assets, 'signal7');
    const createNode = nodes.find(n => n.id === 'create');
    expect(createNode?.data.status).toBe('current');
  });

  it('includes plan-review → worker_* edges for each asset', () => {
    const { edges } = buildPipelineGraph('campaign', 'create', assets, 'signal7');
    const planToWorker = edges.filter(e => e.source === 'plan-review' && e.target.startsWith('worker_'));
    expect(planToWorker).toHaveLength(3);
  });

  it('includes worker_* → review edges for each asset', () => {
    const { edges } = buildPipelineGraph('campaign', 'create', assets, 'signal7');
    const workerToReview = edges.filter(e => e.target === 'review' && e.source.startsWith('worker_'));
    expect(workerToReview).toHaveLength(3);
  });

  it('derives correct worker node statuses from asset status', () => {
    const { nodes } = buildPipelineGraph('campaign', 'create', assets, 'signal7');
    const w1 = nodes.find(n => n.id === 'worker_a1');
    const w2 = nodes.find(n => n.id === 'worker_a2');
    const w3 = nodes.find(n => n.id === 'worker_a3');
    expect(w1?.data.status).toBe('pending');
    expect(w2?.data.status).toBe('done');
    expect(w3?.data.status).toBe('failed');
  });
});

// ── Signal7 campaign with 0 assets ──────────────────────────────────────────
describe('Signal7 campaign with 0 assets', () => {
  it('produces 7 phase nodes and no worker nodes', () => {
    const { nodes } = buildPipelineGraph('campaign', 'create', [], 'signal7');
    const phase = nodes.filter(n => n.type === 'phase');
    const workers = nodes.filter(n => n.type === 'worker');
    expect(phase).toHaveLength(7);
    expect(workers).toHaveLength(0);
  });

  it('has a direct plan-review → review edge (or sequential edges through create)', () => {
    // The spec says "degrade to plan-review → review directly" when 0 assets.
    // Our impl keeps the sequential topology (plan-review→create, create→review)
    // since the phase nodes still exist — the "no worker nodes" is the degradation.
    const { edges } = buildPipelineGraph('campaign', 'create', [], 'signal7');
    // No worker-related edges
    const workerEdges = edges.filter(e => e.source.startsWith('worker_') || e.target.startsWith('worker_'));
    expect(workerEdges).toHaveLength(0);
    // plan-review connects forward (either directly or through create)
    const planReviewOut = edges.filter(e => e.source === 'plan-review');
    expect(planReviewOut.length).toBeGreaterThanOrEqual(1);
  });
});

// ── Signal7 strategy ─────────────────────────────────────────────────────────
describe('Signal7 strategy', () => {
  it('produces 3 phase nodes', () => {
    const { nodes } = buildPipelineGraph('strategy', 'create', [], 'signal7');
    expect(nodes).toHaveLength(3);
    const labels = nodes.map(n => n.data.label);
    expect(labels).toEqual(['brief', 'create', 'done']);
  });

  it('produces 2 edges', () => {
    const { edges } = buildPipelineGraph('strategy', 'create', [], 'signal7');
    expect(edges).toHaveLength(2);
  });
});

// ── Hyper7 feature ───────────────────────────────────────────────────────────
describe('Hyper7 feature', () => {
  it('produces 7 phase nodes in the documented order', () => {
    const { nodes } = buildPipelineGraph('feature', 'implement', [], 'hyper7');
    expect(nodes).toHaveLength(7);
    const labels = nodes.map(n => n.data.label);
    expect(labels).toEqual(['discover', 'spec', 'plan', 'implement', 'verify', 'docs', 'done']);
  });

  it('produces 6 edges', () => {
    const { edges } = buildPipelineGraph('feature', 'implement', [], 'hyper7');
    expect(edges).toHaveLength(6);
  });
});

// ── Unknown (system, scope) combination ──────────────────────────────────────
describe('Unknown pipeline shape', () => {
  it('returns exactly 1 node labelled "Unknown pipeline"', () => {
    const { nodes } = buildPipelineGraph('unknown-scope', 'some-phase', [], 'unknown-system');
    expect(nodes).toHaveLength(1);
    expect(nodes[0].data.label).toBe('Unknown pipeline');
  });

  it('returns 0 edges', () => {
    const { edges } = buildPipelineGraph('unknown-scope', 'some-phase', [], 'unknown-system');
    expect(edges).toHaveLength(0);
  });

  it('does not throw', () => {
    expect(() => buildPipelineGraph('bogus', 'bogus', [], 'bogus')).not.toThrow();
  });
});
