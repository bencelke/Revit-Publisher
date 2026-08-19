import { DragEvent, useRef } from 'react';

export function DropZone({
  onFiles,
  accept = '.json',
  label = 'Drag article JSON files here',
}: {
  onFiles: (files: FileList) => void;
  accept?: string;
  label?: string;
}) {
  const inputRef = useRef<HTMLInputElement>(null);

  function handleDrop(event: DragEvent) {
    event.preventDefault();
    if (event.dataTransfer.files.length > 0) {
      onFiles(event.dataTransfer.files);
    }
  }

  return (
    <div
      className="revit-dropzone"
      onDragOver={(e) => e.preventDefault()}
      onDrop={handleDrop}
      onClick={() => inputRef.current?.click()}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => e.key === 'Enter' && inputRef.current?.click()}
    >
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        multiple
        hidden
        onChange={(e) => e.target.files && onFiles(e.target.files)}
      />
      <strong>{label}</strong>
      <p className="revit-publisher-muted">or choose files · .json · multiple files supported</p>
    </div>
  );
}
