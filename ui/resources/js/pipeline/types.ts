// Pipeline canvas type definitions — pure TypeScript, no React/xyflow imports.

export type NodeStatus = 'pending' | 'current' | 'done' | 'failed' | 'awaiting';

export type System = 'signal7' | 'hyper7';

export type Scope = 'quick' | 'campaign' | 'strategy' | 'feature';

export interface Asset {
  asset_id: string;
  asset_type: string;
  status: string;
}

export interface PipelineNode {
  id: string;
  type: 'phase' | 'worker';
  data: {
    label: string;
    status: NodeStatus;
    phase?: string;
    assetId?: string;
    workerType?: string;
  };
  position: { x: number; y: number };
}

export interface PipelineEdge {
  id: string;
  source: string;
  target: string;
}

export interface BuildPipelineGraphResult {
  nodes: PipelineNode[];
  edges: PipelineEdge[];
}
