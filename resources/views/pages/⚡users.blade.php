<?php

use App\Concerns\RemembersModelRows;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Usuarios')] class extends Component {
    use WithPagination, RemembersModelRows;

    public ?int $supplierId = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'supplier_reservations';

    public string $status = 'active';

    public string $search = '';

    public function mount(): void
    {
        $this->supplierId = Auth::user()->supplier_id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'supplierId' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'alpha_dash:ascii', 'max:255', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in($this->allowedRoleCodes()), Rule::exists('roles', 'code')->where('status', 'active')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $supplierId = Auth::user()->supplier_id ?: $validated['supplierId'];
        $role = Role::query()
            ->select(['id', 'code'])
            ->where('code', $validated['role'])
            ->where('status', 'active')
            ->firstOrFail();

        User::query()->create([
            'supplier_id' => $supplierId,
            'name' => $validated['name'],
            'username' => str($validated['username'])->lower()->toString(),
            'email' => str($validated['email'])->lower()->toString(),
            'password' => $validated['password'],
            'role_id' => $role->id,
            'role' => $role->code,
            'status' => $validated['status'],
        ]);

        $this->reset(['name', 'username', 'email', 'password']);
        $this->role = 'supplier_reservations';
        $this->status = 'active';
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Usuario creado.'));
    }

    /**
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function suppliers(): Collection
    {
        return $this->rememberModels('suppliers.active', Supplier::class, function () {
            return Supplier::query()
                ->select(['id', 'name', 'code'])
                ->active()
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()
            ->select(['id', 'code', 'name'])
            ->where('status', 'active')
            ->whereIn('code', $this->allowedRoleCodes())
            ->orderByRaw("case scope when 'platform' then 0 else 1 end")
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function allowedRoleCodes(): array
    {
        return Auth::user()->supplier_id
            ? ['supplier_admin', 'supplier_reservations', 'supplier_pricing']
            : ['admin', 'supplier_admin', 'supplier_reservations', 'supplier_pricing'];
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with(['supplier:id,name,code', 'portalRole:id,name,code'])
            ->forSupplier(Auth::user()->supplier_id)
            ->when($this->search !== '', function (Builder $query): void {
                $query->search($this->search);
            })
            ->orderBy('name')
            ->paginate(10);
    }
};
?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Usuarios') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Administra acceso al portal, proveedor asignado, rol operativo y estado de cada cuenta.') }}
        </flux:text>
    </div>

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <flux:input wire:model="name" :label="__('Nombre')" placeholder="María López" data-test="user-name" />
            <flux:input wire:model="username" :label="__('Usuario')" placeholder="usuario" data-test="user-username" />
            <flux:input wire:model="email" :label="__('Correo')" type="email" placeholder="maria@proveedor.com" data-test="user-email" />
            <flux:input wire:model="password" :label="__('Contraseña temporal')" type="password" data-test="user-password" />

            @if (! Auth::user()->supplier_id)
                <flux:select wire:model="supplierId" :label="__('Proveedor')" data-test="user-supplier">
                    <flux:select.option value="">{{ __('Administración plataforma') }}</flux:select.option>
                    @foreach ($this->suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }} · {{ $supplier->code }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model="role" :label="__('Rol')" data-test="user-role">
                @foreach ($this->roles as $role)
                    <flux:select.option :value="$role->code">{{ $role->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="status" :label="__('Estado')" data-test="user-status">
                <flux:select.option value="active">{{ __('Activo') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactivo') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button type="submit" variant="primary" icon="user-plus" data-test="user-submit">
                {{ __('Crear usuario') }}
            </flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <flux:heading>{{ __('Directorio de usuarios') }}</flux:heading>
                <flux:text>{{ __('Cuentas con acceso al portal.') }}</flux:text>
            </div>

            <flux:input wire:model.live.debounce.300ms="search" :label="__('Buscar')" placeholder="Nombre, correo o rol" class="md:w-80" data-test="user-search" />
        </div>

        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                <flux:table.column>{{ __('Usuario') }}</flux:table.column>
                <flux:table.column>{{ __('Correo') }}</flux:table.column>
                <flux:table.column>{{ __('Proveedor') }}</flux:table.column>
                <flux:table.column>{{ __('Rol') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Último acceso') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->username }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $user->supplier?->code ?? __('Plataforma') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$user->role === 'admin' ? 'blue' : 'zinc'">
                                {{ $user->portalRole?->name ?? match ($user->role) {
                                    'admin' => __('Administrador plataforma'),
                                    'supplier_admin' => __('Administrador'),
                                    'supplier_pricing' => __('Precios'),
                                    'supplier_reservations', 'supplier_user' => __('Reservas'),
                                    default => $user->role,
                                } }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$user->status === 'active' ? 'green' : 'zinc'">
                                {{ $user->status === 'active' ? __('Activo') : __('Inactivo') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->last_login_at?->diffForHumans() ?? '-' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('Aún no hay usuarios registrados.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
