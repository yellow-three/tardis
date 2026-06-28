<div
    x-data="{
        tags: {{ is_array($value) ? json_encode($value) : '[]' }},
        input: '',
        addTag() {
            if (this.input.trim() && !this.tags.includes(this.input.trim())) {
                this.tags.push(this.input.trim());
                this.input = '';
            }
        },
        removeTag(index) {
            this.tags.splice(index, 1);
        }
    }"
>
    <div class="flex flex-wrap gap-2 mb-2">
        <template x-for="(tag, index) in tags" :key="index">
            <span class="badge badge-primary gap-1">
                <span x-text="tag"></span>
                <button type="button" @click="removeTag(index)" class="btn btn-ghost btn-xs">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </template>
    </div>
    <div class="flex gap-2">
        <input
            type="text"
            x-model="input"
            @keydown.enter.prevent="addTag()"
            placeholder="Add tag..."
            class="input input-bordered flex-1 input-sm"
        />
        <button type="button" @click="addTag()" class="btn btn-primary btn-sm">Add</button>
    </div>
    <input type="hidden" name="{{ $name }}" :value="JSON.stringify(tags)" />
</div>
