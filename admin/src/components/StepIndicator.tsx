const STEPS = ['Upload', 'Analyze', 'Optimize', 'Import'] as const;

export type WorkflowStep = 'upload' | 'analyze' | 'optimize' | 'import' | 'complete';

const stepIndex: Record<WorkflowStep, number> = {
  upload: 0,
  analyze: 1,
  optimize: 2,
  import: 3,
  complete: 4,
};

export function StepIndicator({ current }: { current: WorkflowStep }) {
  const active = stepIndex[current];
  return (
    <ol className="revit-step-indicator">
      {STEPS.map((label, index) => (
        <li
          key={label}
          className={
            index < active ? 'revit-step revit-step--done' : index === active ? 'revit-step revit-step--active' : 'revit-step'
          }
        >
          <span className="revit-step__num">{index + 1}</span>
          <span className="revit-step__label">{label}</span>
        </li>
      ))}
    </ol>
  );
}
