import React from 'react';
import { createRoot } from 'react-dom/client';
import { ReactFlow, Background, Controls, type NodeMouseHandler } from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import buildPipelineGraph from './buildPipelineGraph';
import PhaseNode from './nodes/PhaseNode';
import WorkerNode from './nodes/WorkerNode';
import type { Asset, System, Scope, PipelineNode } from './types';

// ── State shape expected in data-state JSON ───────────────────────────────────
interface PipelineState {
  task_id?: string;
  system?: string;
  scope?: string;
  phase?: string;
  awaiting?: string | null;
  assets?: Asset[];
}

// ── Node type registry ────────────────────────────────────────────────────────
const nodeTypes = {
  phase: PhaseNode,
  worker: WorkerNode,
};

// ── Click bridge ─────────────────────────────────────────────────────────────
// Dispatches a native window CustomEvent that the Alpine x-on: wrapper
// (owned by T9.5) re-emits as a Livewire-observable event.
const handleNodeClick: NodeMouseHandler = (_event, node) => {
  const typedNode = node as PipelineNode;
  window.dispatchEvent(
    new CustomEvent('pipeline-node-clicked', {
      detail: {
        nodeId: typedNode.id,
        nodeType: typedNode.type,
        phase: typedNode.data?.phase ?? null,
        assetId: typedNode.data?.assetId ?? null,
      },
    }),
  );
};

// ── Island app ────────────────────────────────────────────────────────────────
function PipelineApp({ state }: { state: PipelineState }) {
  const system = (state.system ?? 'unknown') as System;
  const scope  = (state.scope  ?? 'unknown') as Scope;
  const phase  = state.phase   ?? '';
  const assets: Asset[] = Array.isArray(state.assets) ? state.assets : [];

  let { nodes, edges } = buildPipelineGraph(scope, phase, assets, system);

  // If the task is in an awaiting state, override the current node's status.
  if (state.awaiting) {
    nodes = nodes.map(n =>
      n.data.status === 'current'
        ? { ...n, data: { ...n.data, status: 'awaiting' as const } }
        : n,
    );
  }

  return (
    <ReactFlow
      nodes={nodes}
      edges={edges}
      nodeTypes={nodeTypes}
      onNodeClick={handleNodeClick}
      fitView
    >
      <Background />
      <Controls />
    </ReactFlow>
  );
}

// ── Mount guard ───────────────────────────────────────────────────────────────
// The compiled bundle may be loaded on pages that do not have a canvas region;
// return early so there are no side effects in those cases.
const mountNode = document.getElementById('pipeline-root');
if (mountNode) {
  let state: PipelineState = {};
  try {
    const raw = mountNode.dataset['state'];
    if (raw) {
      state = JSON.parse(raw) as PipelineState;
    }
  } catch {
    // Malformed JSON — render the unknown-pipeline fallback.
  }

  const reactRoot = createRoot(mountNode);
  reactRoot.render(<PipelineApp state={state} />);
}
