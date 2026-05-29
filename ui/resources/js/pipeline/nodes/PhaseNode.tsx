import React from 'react';
import { Handle, Position, type NodeProps } from '@xyflow/react';

// Local data shape — do not import from ../types to keep this component
// independently compilable without the builder module.
interface PhaseNodeData {
  label: string;
  status: 'pending' | 'current' | 'done' | 'failed' | 'awaiting';
  phase?: string;
  [key: string]: unknown;
}

// ── Status icons (inline SVG, aria-hidden) ────────────────────────────────────

function PendingIcon() {
  return (
    <svg aria-hidden="true" className="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
      <circle cx="10" cy="10" r="8" />
    </svg>
  );
}

function CurrentIcon() {
  return (
    <span
      aria-hidden="true"
      className="inline-block h-4 w-4 rounded-full bg-blue-500 animate-pulse ring-2 ring-blue-500"
    />
  );
}

function DoneIcon() {
  return (
    <svg aria-hidden="true" className="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
    </svg>
  );
}

function FailedIcon() {
  return (
    <svg aria-hidden="true" className="h-4 w-4 text-rose-500" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}

function AwaitingIcon() {
  return (
    <svg aria-hidden="true" className="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9" />
      <path strokeLinecap="round" d="M12 7v5l3 3" />
    </svg>
  );
}

function StatusIcon({ status }: { status: PhaseNodeData['status'] }) {
  switch (status) {
    case 'current':   return <CurrentIcon />;
    case 'done':      return <DoneIcon />;
    case 'failed':    return <FailedIcon />;
    case 'awaiting':  return <AwaitingIcon />;
    case 'pending':
    default:          return <PendingIcon />;
  }
}

// ── PhaseNode component ───────────────────────────────────────────────────────

export default function PhaseNode(props: NodeProps) {
  const data = props.data as PhaseNodeData;
  const { label, status } = data;

  const isCurrent = status === 'current';

  return (
    <div
      aria-current={isCurrent ? 'step' : undefined}
      aria-label={`Phase ${label}: ${status}`}
      className="rounded-lg border px-3 py-2 bg-white dark:bg-gray-900 flex items-center gap-2 min-w-[100px]"
    >
      <Handle type="target" position={Position.Left} />
      <StatusIcon status={status} />
      <span className="text-sm font-medium text-gray-800 dark:text-gray-100">{label}</span>
      <Handle type="source" position={Position.Right} />
    </div>
  );
}
