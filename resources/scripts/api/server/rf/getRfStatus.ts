import http from '@/api/http';

export interface RfStatus {
    // Port launcher menjawab dengan balasan status yang valid.
    online: boolean;
    // Server sudah siap menerima login (online tapi belum ready = masih start).
    ready: boolean;
    players: number;
    // 0 berarti tanpa batas.
    maxSessions: number;
    game: string | null;
    version: string | null;
    error: string | null;
    authPort: number;
}

export default async (uuid: string): Promise<RfStatus> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/rf/status`);
    const attributes = data.attributes;

    return {
        online: attributes.online,
        ready: attributes.ready,
        players: attributes.players,
        maxSessions: attributes.max_sessions,
        game: attributes.game,
        version: attributes.version,
        error: attributes.error,
        authPort: attributes.auth_port,
    };
};
