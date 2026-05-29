import React from 'react';
import { Handle, Position, type NodeProps } from '@xyflow/react';

// Local data shape — do not import from ../types to keep this component
// independently compilable without the builder module.
interface WorkerNodeData {
  label: string;
  status: 'pending' | 'current' | 'done' | 'failed' | 'awaiting';
  workerType?: string;
  assetId?: string;
  [key: string]: unknown;
}

// ── Status icons (inline SVG, aria-hidden) ────────────────────────────────────

function PendingIcon() {
  return (
    <svg aria-hidden="true" className="h-3 w-3 text-gray-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
      <circle cx="10" cy="10" r="8" />
    </svg>
  );
}

function CurrentIcon() {
  return (
    <span
      aria-hidden="true"
      className="inline-block h-3 w-3 rounded-full bg-blue-500 animate-pulse ring-2 ring-blue-500 shrink-0"
    />
  );
}

function DoneIcon() {
  return (
    <svg aria-hidden="true" className="h-3 w-3 text-emerald-500 shrink-0" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
    </svg>
  );
}

function FailedIcon() {
  return (
    <svg aria-hidden="true" className="h-3 w-3 text-rose-500 shrink-0" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}

function AwaitingIcon() {
  return (
    <svg aria-hidden="true" className="h-3 w-3 text-amber-500 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9" />
      <path strokeLinecap="round" d="M12 7v5l3 3" />
    </svg>
  );
}

function StatusIcon({ status }: { status: WorkerNodeData['status'] }) {
  switch (status) {
    case 'current':   return <CurrentIcon />;
    case 'done':      return <DoneIcon />;
    case 'failed':    return <FailedIcon />;
    case 'awaiting':  return <AwaitingIcon />;
    case 'pending':
    default:          return <PendingIcon />;
  }
}

// ── WorkerNode component ──────────────────────────────────────────────────────

export default function WorkerNode(props: NodeProps) {
  const data = props.data as WorkerNodeData;
  const { status, workerType = 'worker', assetId = '' } = data;

  return (
    <div
      aria-label={`Worker ${workerType} for ${assetId}: ${status}`}
      className="rounded-md border px-2 py-1 text-xs bg-white dark:bg-gray-900 flex items-center gap-1.5 min-w-[80px]"
    >
      <Handle type="target" position={Position.Left} />
      <div className="flex flex-col flex-1 overflow-hidden">
        <span className="font-medium text-gray-700 dark:text-gray-200 truncate">{workerType}</span>
        <span className="text-gray-400 dark:text-gray-500 truncate">{assetId}</span>
      </div>
      <StatusIcon status={status} />
      <Handle type="source" position={Position.Right} />
    </div>
  );
}
