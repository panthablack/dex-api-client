@props(['results', 'type' => \App\Enums\ResourceType::CLIENT->value])

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark">
      <tr>
        <th>Row #</th>
        <th>Status</th>
        @if ($type === \App\Enums\ResourceType::CLIENT->value)
          <th>Client ID</th>
          <th>Name</th>
        @elseif($type === \App\Enums\ResourceType::CASE->value)
          <th>Case ID</th>
          <th>Client ID</th>
        @elseif($type === \App\Enums\ResourceType::SESSION->value)
          <th>Session ID</th>
          <th>Case ID</th>
        @endif
        <th>Result/Error</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($results as $index => $result)
        <tr class="{{ $result['status'] === 'success' ? 'table-success' : 'table-danger' }}">
          <td>{{ $index + 1 }}</td>
          <td>
            @if ($result['status'] === 'success')
              <span class="badge bg-success">Success</span>
            @else
              <span class="badge bg-danger">Error</span>
            @endif
          </td>

          @if ($type === \App\Enums\ResourceType::CLIENT->value)
            <td>
              @if (isset($result['client_data']['client_id']))
                {{ $result['client_data']['client_id'] }}
              @else
                <em class="text-muted">N/A</em>
              @endif
            </td>
            <td>
              @if (isset($result['client_data']['first_name']) && isset($result['client_data']['last_name']))
                {{ $result['client_data']['first_name'] }} {{ $result['client_data']['last_name'] }}
              @else
                <em class="text-muted">N/A</em>
              @endif
            </td>
          @elseif($type === \App\Enums\ResourceType::CASE->value)
            <td>
              @if (isset($result['case_data']['case_id']))
                {{ $result['case_data']['case_id'] }}
              @else
                <em class="text-muted">N/A</em>
              @endif
            </td>
            <td>
              @if (isset($result['case_data']['client_id']))
                {{ $result['case_data']['client_id'] }}
              @else
                <em class="text-muted">N/A</em>
              @endif
            </td>
          @elseif($type === \App\Enums\ResourceType::SESSION->value)
            <td>
              @if (isset($result['session_data']['session_id']))
                {{ $result['session_data']['session_id'] }}
              @else
                <em class="text-muted">N/A</em>
              @endif
            </td>
            <td>
              @if (isset($result['session_data']['case_id']))
                {{ $result['session_data']['case_id'] }}
              @else
                <em class="text-muted">N/A</em>
              @endif
            </td>
          @endif

          <td>
            @if ($result['status'] === 'success')
              @php
                $submissionId = null;
                if (isset($result['result'])) {
                    if (is_array($result['result']) && isset($result['result']['SubmissionID'])) {
                        $submissionId = $result['result']['SubmissionID'];
                    } elseif (is_object($result['result']) && property_exists($result['result'], 'SubmissionID')) {
                        $submissionId = $result['result']->SubmissionID;
                    }
                }
              @endphp
              @if ($submissionId)
                <small class="text-success">Submission ID: {{ $submissionId }}</small>
              @else
                <small class="text-success">Successfully processed</small>
              @endif
            @else
              <small class="text-danger">{{ $result['error'] ?? 'Unknown error' }}</small>
            @endif
          </td>
          <td>
            @if ($result['status'] === 'success' && isset($submissionId) && $submissionId)
              <button class="btn btn-outline-info btn-sm" @click="checkSubmissionStatus('{{ $submissionId }}')"
                title="Check Status">
                <i class="fas fa-search"></i>
              </button>
            @endif
            <button class="btn btn-outline-secondary btn-sm" @click="showDetails({{ $index }})"
              title="View Details">
              <i class="fas fa-eye"></i>
            </button>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
