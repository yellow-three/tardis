<?php

declare(strict_types=1);

namespace Tardis\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Accounts of the host's user model, as the Users screen and the profile page
 * edit them: which columns hold the name, email and password
 * (tardis.users.columns), how they are validated and written.
 *
 * Who may do what is not decided here; the screens ask PrivilegeGuard first.
 */
final class UserAccounts
{
    /** @var array<string, bool> */
    private array $columnCache = [];

    /** @return class-string<Model> */
    public function model(): string
    {
        return (string) config('auth.providers.users.model');
    }

    /**
     * The column behind "name", "email" or "password", or null when the
     * table has no such column.
     */
    public function column(string $field): ?string
    {
        $column = (string) (config("tardis.users.columns.{$field}") ?: $field);

        return $this->hasColumn($column) ? $column : null;
    }

    /**
     * Validation rules for the account form fields (prefix "userForm.").
     *
     * @return array<string, mixed>
     */
    public function rules(mixed $ignoreKey = null, bool $passwordRequired = true, string $prefix = 'userForm.'): array
    {
        $rules = [];
        $model = new ($this->model());

        if ($this->column('name') !== null) {
            $rules[$prefix.'name'] = ['required', 'string', 'max:255'];
        }

        if (($email = $this->column('email')) !== null) {
            $table = $model->getConnectionName() ? $model->getConnectionName().'.'.$model->getTable() : $model->getTable();
            $unique = Rule::unique($table, $email);
            $rules[$prefix.'email'] = ['required', 'email', 'max:255', $ignoreKey === null ? $unique : $unique->ignore($ignoreKey, $model->getKeyName())];
        }

        if ($this->column('password') !== null) {
            $rule = [$passwordRequired ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'];
            $rules[$prefix.'password'] = $rule;
        }

        return $rules;
    }

    /**
     * @param  array{name?: string, email?: string, password?: string}  $data
     */
    public function create(array $data): Model
    {
        $user = new ($this->model());
        $this->fill($user, $data);
        $user->save();

        return $user;
    }

    /**
     * Change the name and email, and the password only when one was given.
     *
     * @param  array{name?: string, email?: string, password?: string}  $data
     */
    public function update(Model $user, array $data): void
    {
        $this->fill($user, $data);
        $user->save();
    }

    /** Delete a user together with their Tardis role links. */
    public function delete(Model $user): void
    {
        DB::transaction(function () use ($user) {
            DB::table('tardis_role_user')->where('user_id', $user->getKey())->delete();
            $user->delete();
        });
    }

    public function passwordMatches(Model $user, string $password): bool
    {
        $column = $this->column('password');

        return $column !== null && is_string($user->getAttribute($column)) && Hash::check($password, $user->getAttribute($column));
    }

    /**
     * @return array{name: string, email: string}
     */
    public function describe(Model $user): array
    {
        $name = $this->column('name');
        $email = $this->column('email');

        return [
            'name' => $name !== null ? (string) $user->getAttribute($name) : '',
            'email' => $email !== null ? (string) $user->getAttribute($email) : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Model $user, array $data): void
    {
        foreach (['name', 'email'] as $field) {
            if (($column = $this->column($field)) !== null && array_key_exists($field, $data)) {
                $user->setAttribute($column, trim((string) $data[$field]));
            }
        }

        $password = (string) ($data['password'] ?? '');

        if ($password !== '' && ($column = $this->column('password')) !== null) {
            // Hash::make() output is recognised by the "hashed" cast, so a
            // model with that cast does not hash it a second time.
            $user->setAttribute($column, Hash::make($password));
        }
    }

    private function hasColumn(string $column): bool
    {
        $model = new ($this->model());

        return $this->columnCache[$column] ??= Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column);
    }
}
