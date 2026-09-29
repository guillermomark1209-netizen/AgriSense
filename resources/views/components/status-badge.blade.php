@props(['status'=>'unknown'])
<span @class(['status-badge','status-good'=>in_array(strtolower($status),['healthy','online','verified','resolved']),'status-warning'=>in_array(strtolower($status),['warning','pending']),'status-critical'=>in_array(strtolower($status),['critical','rejected','failed'])])><span aria-hidden="true">●</span>{{ ucfirst($status) }}</span>
