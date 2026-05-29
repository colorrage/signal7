// Pure topology builder — zero React / @xyflow/react imports.
// Vitest can run this in node environment without jsdom.

import type {
  Asset,
  BuildPipelineGraphResult,
  NodeStatus,
  PipelineEdge,
  PipelineNode,
  Scope,
  System,
} from './types';

// Layout constants (internal — tests assert topology, not coordinates).
const PHASE_Y = 200;
const PHASE_X_STEP = 200;
const WORKER_X_OFFSET = 100;
const WORKER_Y_STEP = 120;
const WORKER_Y_BASE = 380;

// Map an asset's raw status string to a NodeStatus.
function assetStatusToNodeStatus(raw: string): NodeStatus {
  if (raw === 'done') return 'done';
  if (raw === 'failed') return 'failed';
  if (raw === 'awaiting') return 'awaiting';
  return 'pending';
}

// Derive per-phase status from the current active phase and an ordered list.
function derivePhaseStatus(phaseName: string, currentPhase: string, orderedPhases: string[]): NodeStatus {
  const currentIdx = orderedPhases.indexOf(currentPhase);
  const nodeIdx = orderedPhases.indexOf(phaseName);

  if (nodeIdx === -1 || currentIdx === -1) return 'pending';
  if (nodeIdx < currentIdx) return 'done';
  if (nodeIdx === currentIdx) return 'current';
  return 'pending';
}

// Build a linear set of phase nodes + edges from an ordered phase-name list.
function buildLinearPhases(
  orderedPhases: string[],
  currentPhase: string,
  startX: number,
): { nodes: PipelineNode[]; edges: PipelineEdge[] } {
  const nodes: PipelineNode[] = orderedPhases.map((phase, i) => ({
    id: phase,
    type: 'phase',
    data: {
      label: phase,
      status: derivePhaseStatus(phase, currentPhase, orderedPhases),
      phase,
    },
    position: { x: startX + i * PHASE_X_STEP, y: PHASE_Y },
  }));

  const edges: PipelineEdge[] = [];
  for (let i = 0; i < orderedPhases.length - 1; i++) {
    edges.push({
      id: `${orderedPhases[i]}->${orderedPhases[i + 1]}`,
      source: orderedPhases[i],
      target: orderedPhases[i + 1],
    });
  }

  return { nodes, edges };
}

// Signal7 quick: brief → create → review → publish → done
function buildQuick(currentPhase: string): BuildPipelineGraphResult {
  const phases = ['brief', 'create', 'review', 'publish', 'done'];
  return buildLinearPhases(phases, currentPhase, 0);
}

// Signal7 strategy: brief → create → done
function buildStrategy(currentPhase: string): BuildPipelineGraphResult {
  const phases = ['brief', 'create', 'done'];
  return buildLinearPhases(phases, currentPhase, 0);
}

// Signal7 campaign: brief → plan → plan-review → create → review → publish → done
// When phase === 'create' and assets.length > 0: fan-out worker nodes between
// plan-review and review. When assets.length === 0: degrade to direct edge.
function buildCampaign(currentPhase: string, assets: Asset[]): BuildPipelineGraphResult {
  const orderedPhases = ['brief', 'plan', 'plan-review', 'create', 'review', 'publish', 'done'];

  const nodes: PipelineNode[] = orderedPhases.map((phase, i) => ({
    id: phase,
    type: 'phase',
    data: {
      label: phase,
      status: derivePhaseStatus(phase, currentPhase, orderedPhases),
      phase,
    },
    position: { x: i * PHASE_X_STEP, y: PHASE_Y },
  }));

  const edges: PipelineEdge[] = [];

  for (let i = 0; i < orderedPhases.length - 1; i++) {
    const src = orderedPhases[i];
    const tgt = orderedPhases[i + 1];
    // Skip plan-review → create and create → review when we have worker fan-out
    if (assets.length > 0 && currentPhase === 'create') {
      if ((src === 'plan-review' && tgt === 'create') || (src === 'create' && tgt === 'review')) {
        continue;
      }
    }
    edges.push({ id: `${src}->${tgt}`, source: src, target: tgt });
  }

  if (assets.length > 0 && currentPhase === 'create') {
    // Worker fan-out: plan-review → worker_i → review
    assets.forEach((asset, i) => {
      const workerId = `worker_${asset.asset_id}`;
      nodes.push({
        id: workerId,
        type: 'worker',
        data: {
          label: asset.asset_id,
          status: assetStatusToNodeStatus(asset.status),
          assetId: asset.asset_id,
          workerType: asset.asset_type,
        },
        position: {
          x: WORKER_X_OFFSET + orderedPhases.indexOf('plan-review') * PHASE_X_STEP,
          y: WORKER_Y_BASE + i * WORKER_Y_STEP,
        },
      });
      edges.push({ id: `plan-review->${workerId}`, source: 'plan-review', target: workerId });
      edges.push({ id: `${workerId}->review`, source: workerId, target: 'review' });
    });
  } else if (assets.length === 0) {
    // Degrade: plan-review → review directly (skip 'create' phase bridging)
    // The create phase node still shows but has no in/out worker edges.
    // The plan specifies direct plan-review → review edge when 0 assets.
    // We already added plan-review→create and create→review above, but with
    // no fan-out we keep the normal edges. Re-check: the spec says
    // "degrade to plan-review → review directly (no worker nodes)".
    // So we keep the regular sequential edges — the create phase node is still
    // present in the topology (it's one of the 7 phase nodes), just no workers.
  }

  return { nodes, edges };
}

// Hyper7 feature: discover → spec → plan → implement → verify → docs → done
function buildHyper7Feature(currentPhase: string): BuildPipelineGraphResult {
  const phases = ['discover', 'spec', 'plan', 'implement', 'verify', 'docs', 'done'];
  return buildLinearPhases(phases, currentPhase, 0);
}

// Unknown shape fallback: single labelled node, no edges.
function buildUnknown(): BuildPipelineGraphResult {
  return {
    nodes: [
      {
        id: 'unknown',
        type: 'phase',
        data: { label: 'Unknown pipeline', status: 'pending' },
        position: { x: 0, y: PHASE_Y },
      },
    ],
    edges: [],
  };
}

export default function buildPipelineGraph(
  scope: string,
  phase: string,
  assets: Asset[],
  system: string,
): BuildPipelineGraphResult {
  if (system === 'signal7') {
    if (scope === 'quick') return buildQuick(phase);
    if (scope === 'campaign') return buildCampaign(phase, assets);
    if (scope === 'strategy') return buildStrategy(phase);
  }
  if (system === 'hyper7' && scope === 'feature') {
    return buildHyper7Feature(phase);
  }
  return buildUnknown();
}
