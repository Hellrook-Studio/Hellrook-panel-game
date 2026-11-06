import React from 'react';
import useSWR from 'swr';
import isEqual from 'react-fast-compare';
import classNames from 'classnames';
import { faFileCode, faGlobe, faPlug, faSignal } from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { Alert } from '@/components/elements/alert';
import { Button } from '@/components/elements/button/index';
import getRfStatus, { RfStatus } from '@/api/server/rf/getRfStatus';

const Row = ({ label, value, copy }: { label: string; value: React.ReactNode; copy?: string | number }) => (
    <div className={'flex items-center justify-between py-2 border-b border-neutral-600 last:border-0'}>
        <span className={'text-neutral-300 text-sm'}>{label}</span>
        {copy !== undefined ? (
            <CopyOnClick text={copy}>
                <span className={'font-mono text-neutral-50'}>{value}</span>
            </CopyOnClick>
        ) : (
            <span className={'font-mono text-neutral-50'}>{value}</span>
        )}
    </div>
);

const StatusBadge = ({ status, failed }: { status?: RfStatus; failed: boolean }) => {
    const [label, colour] = failed
        ? ['Unknown', 'bg-neutral-400']
        : !status
        ? ['Checking…', 'bg-neutral-400']
        : !status.online
        ? ['Offline', 'bg-red-500']
        : !status.ready
        ? ['Starting', 'bg-yellow-500']
        : ['Online', 'bg-green-500'];

    return (
        <span className={'inline-flex items-center text-lg font-medium text-neutral-50'}>
            <span className={classNames('inline-block w-3 h-3 rounded-full mr-2', colour)} />
            {label}
        </span>
    );
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const allocations = ServerContext.useStoreState((state) => state.server.data!.allocations, isEqual);
    const variables = ServerContext.useStoreState((state) => state.server.data!.variables, isEqual);
    const powerState = ServerContext.useStoreState((state) => state.status.value);

    const { data: status, error } = useSWR<RfStatus>(['server:rf:status', uuid], () => getRfStatus(uuid), {
        refreshInterval: 15000,
    });

    const main = allocations.find((allocation) => allocation.isDefault);
    if (!main) {
        return (
            <ServerContentBlock title={'RF Online'}>
                <Alert type={'warning'}>This server has no main allocation yet.</Alert>
            </ServerContentBlock>
        );
    }

    const value = (env: string) => {
        const variable = variables.find((v) => v.envVariable === env);
        return variable ? variable.serverValue ?? variable.defaultValue : undefined;
    };
    const host = main.alias || (['0.0.0.0', '::'].includes(main.ip) ? window.location.hostname : main.ip);
    const authPort = main.port + 1;
    const webEnabled = value('WEB_ENABLED') !== '0';
    const webPort = Number(value('WEB_PORT')) || main.port + 2;
    const website = `http://${host}:${webPort}`;
    const assigned = (port: number) => allocations.some((allocation) => allocation.port === port);
    const launcherIni = `[Server]\nHost = ${host}\nGamePort = ${main.port}\nAuthPort = ${authPort}\n`;

    return (
        <ServerContentBlock title={'RF Online'}>
            {!assigned(authPort) && (
                <Alert type={'danger'} className={'mb-4'}>
                    Launcher port {authPort} is not assigned to this server, so players cannot log in from the launcher.
                    Ask an admin to add allocation {authPort}.
                </Alert>
            )}
            {webEnabled && !assigned(webPort) && (
                <Alert type={'warning'} className={'mb-4'}>
                    Website port {webPort} is not assigned to this server, so the website cannot be reached from
                    outside. Ask an admin to add allocation {webPort}.
                </Alert>
            )}
            <div className={'grid gap-4 md:grid-cols-2'}>
                <TitledGreyBox title={'Server status'} icon={faSignal}>
                    <div className={'flex items-center justify-between pb-2'}>
                        <StatusBadge status={status} failed={!!error} />
                        {status?.online && (
                            <span className={'text-neutral-200'}>
                                <span className={'text-2xl font-medium text-neutral-50'}>{status.players}</span>
                                {status.maxSessions > 0 ? ` / ${status.maxSessions}` : ''} players online
                            </span>
                        )}
                    </div>
                    {status?.online && (
                        <>
                            {status.game && <Row label={'Game'} value={status.game} />}
                            {status.version && <Row label={'Version'} value={status.version} />}
                        </>
                    )}
                    {status && !status.online && (
                        <p className={'text-sm text-neutral-300 pt-2'}>
                            {powerState === 'running' || powerState === 'starting'
                                ? 'The server process is running, but the launcher port does not answer yet.'
                                : 'The server is not running. Start it from the Console tab.'}
                        </p>
                    )}
                    {error && <p className={'text-sm text-neutral-300 pt-2'}>The status could not be checked.</p>}
                </TitledGreyBox>
                <TitledGreyBox title={'Connection'} icon={faPlug}>
                    <Row label={'Server address'} value={host} copy={host} />
                    <Row label={'Game port'} value={main.port} copy={main.port} />
                    <Row label={'Launcher port'} value={authPort} copy={authPort} />
                    <Row
                        label={'Website'}
                        value={
                            webEnabled ? (
                                <a href={website} target={'_blank'} rel={'noreferrer'} className={'text-blue-300'}>
                                    {website}
                                </a>
                            ) : (
                                'disabled'
                            )
                        }
                    />
                </TitledGreyBox>
                <TitledGreyBox title={'launcher.ini for players'} icon={faFileCode} className={'md:col-span-2'}>
                    <p className={'text-sm text-neutral-300 mb-3'}>
                        Put this in the <code>[Server]</code> section of <code>launcher.ini</code> in the RF client
                        folder. Host is the address only, without a port.
                    </p>
                    <pre className={'font-mono text-sm bg-neutral-900 rounded p-3 text-neutral-100 overflow-x-auto'}>
                        {launcherIni}
                    </pre>
                    <div className={'flex justify-end mt-3'}>
                        <CopyOnClick text={launcherIni} showInNotification={false}>
                            <Button size={Button.Sizes.Small}>Copy</Button>
                        </CopyOnClick>
                    </div>
                </TitledGreyBox>
                {webEnabled && (
                    <TitledGreyBox title={'Website and control panel'} icon={faGlobe} className={'md:col-span-2'}>
                        <p className={'text-sm text-neutral-300'}>
                            Player accounts, rankings, and the game control panel run on the server&apos;s own website:{' '}
                            <a href={website} target={'_blank'} rel={'noreferrer'} className={'text-blue-300'}>
                                {website}
                            </a>
                            . The first admin login uses <code>SUPER_PASSWORD</code> from{' '}
                            <code>settings/settings.cfg</code>.
                        </p>
                    </TitledGreyBox>
                )}
            </div>
        </ServerContentBlock>
    );
};
