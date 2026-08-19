import { useEffect, useState } from 'react';
import { fetchIssues, updateIssueStatus } from '../api/operations';

interface IssueRow {
  issue_id: number;
  issue_type: string;
  severity: string;
  status: string;
  vehicle: string;
  title: string;
  explanation: string;
  recommended_action: string;
}

export function NeedsAttentionPage() {
  const [issues, setIssues] = useState<IssueRow[]>([]);

  function load() {
    fetchIssues().then((data) => setIssues(data as IssueRow[]));
  }

  useEffect(() => { load(); }, []);

  async function setStatus(id: number, status: string) {
    await updateIssueStatus(id, status);
    load();
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>Needs Attention</h1>
      <p className="revit-publisher-muted">Unified operator queue from scheduled audits.</p>
      <table className="revit-publisher-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Severity</th>
            <th>Vehicle</th>
            <th>Issue</th>
            <th>Action</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          {issues.map((issue) => (
            <tr key={issue.issue_id}>
              <td>{issue.issue_type}</td>
              <td><span className={`revit-severity revit-severity--${issue.severity}`}>{issue.severity}</span></td>
              <td>{issue.vehicle || '—'}</td>
              <td>
                <strong>{issue.title}</strong>
                <div className="revit-publisher-muted">{issue.explanation}</div>
              </td>
              <td>{issue.recommended_action}</td>
              <td>
                <select value={issue.status} onChange={(e) => setStatus(issue.issue_id, e.target.value)}>
                  <option value="open">Open</option>
                  <option value="acknowledged">Acknowledged</option>
                  <option value="resolved">Resolved</option>
                  <option value="ignored">Ignored</option>
                </select>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
