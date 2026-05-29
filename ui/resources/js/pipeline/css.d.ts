// Allow side-effect CSS imports used by @xyflow/react and similar packages.
declare module '*.css' {
  const stylesheet: string;
  export default stylesheet;
}
