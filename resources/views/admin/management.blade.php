@extends('layouts.app')
@section('title',ucwords(str_replace('-',' ',$section)))
@section('subtitle','Administration · Farm records and account access.')
@section('content')
@if($section === 'users')<a class="agri-btn agri-btn-primary mb-5" href="{{ route('admin.users.create') }}">Create user</a>@endif
<section class="agri-card p-5 table-wrap"><table><thead><tr><th>ID</th>@foreach($columns as $column)<th>{{ ucwords(str_replace('_',' ',$column)) }}</th>@endforeach<th>Action</th></tr></thead><tbody>@forelse($records as $record)<tr><td>{{ $record->id }}</td>@foreach($columns as $column)<td>{{ is_array($record->{$column}) ? json_encode($record->{$column}) : $record->{$column} }}</td>@endforeach<td>
@if($section==='users')<a class="text-link" href="{{ route('admin.users.edit', $record) }}">Edit user</a>
@if($record->id !== auth()->id())<form method="POST" action="{{ route('admin.users.role',$record) }}">@csrf @method('PATCH')<label class="sr-only" for="role-{{ $record->id }}">Account role</label><select id="role-{{ $record->id }}" name="role"><option value="user" @selected($record->role === 'user')>User</option><option value="admin" @selected($record->hasRole('admin'))>Admin</option></select><button class="text-link mt-2">Update role</button></form><form method="POST" action="{{ route('admin.users.destroy', $record) }}" data-confirm="Delete this account? Accounts with farm records cannot be deleted.">@csrf @method('DELETE')<button class="text-link mt-2">Delete user</button></form>@endif
@elseif($section==='crops')<a class="text-link" href="{{ route('crops.show',$record) }}">Manage crop</a>
@elseif($section==='devices')<a class="text-link" href="{{ route('devices.show',$record) }}">Manage device</a>
@elseif($section==='alerts' && $record->status!=='resolved')<form method="POST" action="{{ route('alerts.resolve',$record) }}">@csrf @method('PATCH')<button class="text-link">Resolve</button></form>
@elseif($section==='conversations')<details><summary>Review messages</summary>@foreach($record->messages as $message)<p class="my-3 text-xs"><strong>{{ $message->role }}</strong> {{ $message->content }}</p>@endforeach</details>
@else<span class="muted">Read only</span>@endif
</td></tr>@empty<tr><td colspan="{{ count($columns)+2 }}">No records yet.</td></tr>@endforelse</tbody></table><div class="mt-5">{{ $records->links() }}</div></section>
@endsection
