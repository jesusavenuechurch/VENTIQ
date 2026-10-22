/** A JSON request to our own routes, with the CSRF token Laravel wants. */
export async function json<T>(url: string, init?: Omit<RequestInit, 'body'> & { body?: unknown }): Promise<{ ok: boolean; status: number; data: T }> {
    const res = await fetch(url, {
        ...init,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '',
            ...init?.headers,
        },
        body: init?.body === undefined ? undefined : JSON.stringify(init.body),
    });
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, data: data as T };
}
