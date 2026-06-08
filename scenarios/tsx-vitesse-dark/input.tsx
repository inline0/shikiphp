import { useEffect, useState } from "react";

interface User {
    id: number;
    name: string;
    roles: readonly string[];
}

type Props = {
    endpoint: string;
    onLoad?: (u: User) => void;
};

export const Profile = ({ endpoint, onLoad }: Props) => {
    const [user, setUser] = useState<User | null>(null);

    useEffect(() => {
        fetch(endpoint)
            .then((r) => r.json() as Promise<User>)
            .then((u) => {
                setUser(u);
                onLoad?.(u);
            });
    }, [endpoint]);

    if (!user) return <p>Loading…</p>;

    return (
        <div className="profile">
            <span>{`${user.name} #${user.id}`}</span>
        </div>
    );
};
