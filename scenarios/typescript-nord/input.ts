type Json = string | number | boolean | null | Json[] | { [k: string]: Json };

interface Repository<T> {
    findById(id: number): Promise<T | undefined>;
    save(entity: T): Promise<void>;
}

enum Status {
    Active = "ACTIVE",
    Archived = "ARCHIVED",
}

class MemoryRepo<T extends { id: number }> implements Repository<T> {
    private store = new Map<number, T>();

    async findById(id: number): Promise<T | undefined> {
        return this.store.get(id);
    }

    async save(entity: T): Promise<void> {
        this.store.set(entity.id, entity);
    }
}

const repo = new MemoryRepo<{ id: number; status: Status }>();
void repo.save({ id: 1, status: Status.Active });
const tagged = `id=${1} status=${Status.Active}` as const;
console.log(tagged);
