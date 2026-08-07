(() => {
    'use strict';


    /*
    |--------------------------------------------------------------------------
    | Aplicação
    |--------------------------------------------------------------------------
    */

    const app =
        document.getElementById(
            'dashboard-app'
        );

    if (!app) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Configuração
    |--------------------------------------------------------------------------
    */

    const refreshInterval =
        Number(
            app.dataset.refreshInterval
            || 15000
        );

    const api = {
        resumo:
            app.dataset.apiResumo,

        exportacoes:
            app.dataset.apiExportacoes,

        detalhe:
            app.dataset.apiDetalhe,

        reenviar:
            app.dataset.apiReenviar,

        reprocessar:
            app.dataset.apiReprocessar,
    };


    /*
    |--------------------------------------------------------------------------
    | Elementos
    |--------------------------------------------------------------------------
    */

    const elements = {

        connectionDot:
            document.getElementById(
                'connection-status-dot'
            ),

        connectionText:
            document.getElementById(
                'connection-status-text'
            ),

        lastMovementDate:
            document.getElementById(
                'last-movement-date'
            ),

        lastMovementStatus:
            document.getElementById(
                'last-movement-status'
            ),

        lastFileName:
            document.getElementById(
                'last-file-name'
            ),

        lastRecordCount:
            document.getElementById(
                'last-record-count'
            ),

        lastDuration:
            document.getElementById(
                'last-duration'
            ),

        runningCount:
            document.getElementById(
                'running-count'
            ),

        failureCount:
            document.getElementById(
                'failure-count'
            ),

        lastSuccessDate:
            document.getElementById(
                'last-success-date'
            ),

        lastSuccessTime:
            document.getElementById(
                'last-success-time'
            ),

        lastDashboardUpdate:
            document.getElementById(
                'last-dashboard-update'
            ),

        exportExcel:
            document.getElementById(
                'export-excel'
            ),

        exportPdf:
            document.getElementById(
                'export-pdf'
            ),

        dialog:
            document.getElementById(
                'execution-dialog'
            ),

        detailClose:
            document.getElementById(
                'detail-close'
            ),

        detailLoading:
            document.getElementById(
                'detail-loading'
            ),

        detailContent:
            document.getElementById(
                'detail-content'
            ),

        detailTitle:
            document.getElementById(
                'detail-title'
            ),

        detailMovement:
            document.getElementById(
                'detail-movement'
            ),

        detailType:
            document.getElementById(
                'detail-type'
            ),

        detailStatus:
            document.getElementById(
                'detail-status'
            ),

        detailRecords:
            document.getElementById(
                'detail-records'
            ),

        detailSize:
            document.getElementById(
                'detail-size'
            ),

        detailDuration:
            document.getElementById(
                'detail-duration'
            ),

        detailFile:
            document.getElementById(
                'detail-file'
            ),

        detailRemotePath:
            document.getElementById(
                'detail-remote-path'
            ),

        detailSha:
            document.getElementById(
                'detail-sha'
            ),

        detailComparisonSection:
            document.getElementById(
                'detail-comparison-section'
            ),

        detailOriginId:
            document.getElementById(
                'detail-origin-id'
            ),

        detailOriginRecords:
            document.getElementById(
                'detail-origin-records'
            ),

        detailCurrentRecords:
            document.getElementById(
                'detail-current-records'
            ),

        detailOriginSize:
            document.getElementById(
                'detail-origin-size'
            ),

        detailCurrentSize:
            document.getElementById(
                'detail-current-size'
            ),

        detailRecordDifference:
            document.getElementById(
                'detail-record-difference'
            ),

        detailContentChanged:
            document.getElementById(
                'detail-content-changed'
            ),

        detailOriginSha:
            document.getElementById(
                'detail-origin-sha'
            ),

        detailActionsSection:
            document.getElementById(
                'detail-actions-section'
            ),

        detailActionsHelp:
            document.getElementById(
                'detail-actions-help'
            ),

        detailResend:
            document.getElementById(
                'detail-resend'
            ),

        detailReprocess:
            document.getElementById(
                'detail-reprocess'
            ),

        detailActionFeedback:
            document.getElementById(
                'detail-action-feedback'
            ),

        actionConfirmDialog:
            document.getElementById(
                'action-confirm-dialog'
            ),

        actionConfirmTitle:
            document.getElementById(
                'action-confirm-title'
            ),

        actionConfirmMessage:
            document.getElementById(
                'action-confirm-message'
            ),

        actionConfirmCancel:
            document.getElementById(
                'action-confirm-cancel'
            ),

        actionConfirmSubmit:
            document.getElementById(
                'action-confirm-submit'
            ),

        detailErrorSection:
            document.getElementById(
                'detail-error-section'
            ),

        detailError:
            document.getElementById(
                'detail-error'
            ),

        detailTimeline:
            document.getElementById(
                'detail-timeline'
            ),
    };


    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    */

    const statusLabels = {

        AGUARDANDO:
            'Aguardando',

        CONSULTANDO:
            'Consultando',

        GERANDO_ARQUIVO:
            'Gerando arquivo',

        VALIDANDO:
            'Validando',

        ENVIANDO:
            'Enviando',

        CONFIRMANDO_ENVIO:
            'Confirmando envio',

        CONCLUIDO:
            'Concluído',

        FALHOU:
            'Falhou',

        CANCELADO:
            'Cancelado',
    };


    const typeLabels = {

        NORMAL:
            'Normal',

        REPROCESSAMENTO:
            'Reprocessamento',

        REENVIO:
            'Reenvio',
    };


    const processingStatuses =
        new Set([
            'AGUARDANDO',
            'CONSULTANDO',
            'GERANDO_ARQUIVO',
            'VALIDANDO',
            'ENVIANDO',
            'CONFIRMANDO_ENVIO',
        ]);


    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    async function request(
        url,
        options = {}
    ) {

        const method =
            String(
                options.method
                || 'GET'
            ).toUpperCase();


        const headers = {
            Accept:
                'application/json',
        };


        const fetchOptions = {
            method,
            headers,
            cache:
                'no-store',
            credentials:
                'same-origin',
        };


        if (method !== 'GET') {

            headers['Content-Type'] =
                'application/json';

            headers['X-Requested-With'] =
                'XMLHttpRequest';

            fetchOptions.body =
                JSON.stringify(
                    options.body
                    || {}
                );
        }


        const response =
            await fetch(
                url,
                fetchOptions
            );


        let payload;


        try {

            payload =
                await response.json();

        } catch {

            throw new Error(
                'A API retornou uma resposta inválida.'
            );
        }


        if (
            !response.ok
            || payload.success !== true
        ) {

            throw new Error(
                payload.message
                || 'Não foi possível concluir a requisição.'
            );
        }


        return payload.data;
    }


    /*
    |--------------------------------------------------------------------------
    | Formatação
    |--------------------------------------------------------------------------
    */

    function formatMovementDate(value) {

        if (!value) {
            return '—';
        }


        const parts =
            value.split('-');


        if (parts.length !== 3) {
            return value;
        }


        return (
            `${parts[2]}/${parts[1]}/${parts[0]}`
        );
    }


    function formatDateTime(value) {

        if (!value) {
            return '—';
        }


        const date =
            new Date(value);


        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return value;
        }


        return new Intl.DateTimeFormat(
            'pt-BR',
            {
                timeZone:
                    'America/Fortaleza',

                day:
                    '2-digit',

                month:
                    '2-digit',

                year:
                    'numeric',

                hour:
                    '2-digit',

                minute:
                    '2-digit',

                second:
                    '2-digit',
            }
        ).format(date);
    }


    function formatTime(value) {

        if (!value) {
            return '—';
        }


        const date =
            new Date(value);


        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return '—';
        }


        return new Intl.DateTimeFormat(
            'pt-BR',
            {
                timeZone:
                    'America/Fortaleza',

                hour:
                    '2-digit',

                minute:
                    '2-digit',

                second:
                    '2-digit',
            }
        ).format(date);
    }


    function formatNumber(value) {

        if (
            value === null
            || value === undefined
        ) {
            return '—';
        }


        return new Intl.NumberFormat(
            'pt-BR'
        ).format(value);
    }


    function formatSignedNumber(value) {

        if (
            value === null
            || value === undefined
        ) {
            return '—';
        }


        const number =
            Number(value);


        if (Number.isNaN(number)) {
            return '—';
        }


        if (number > 0) {
            return `+${formatNumber(number)}`;
        }


        return formatNumber(number);
    }


    function formatBytes(bytes) {

        if (
            bytes === null
            || bytes === undefined
        ) {
            return '—';
        }


        if (bytes < 1024) {

            return `${bytes} B`;
        }


        if (
            bytes
            < 1024 * 1024
        ) {

            return `${(
                bytes / 1024
            ).toLocaleString(
                'pt-BR',
                {
                    maximumFractionDigits:
                        1,
                }
            )
                } KB`;
        }


        return `${(
            bytes
            / 1024
            / 1024
        ).toLocaleString(
            'pt-BR',
            {
                maximumFractionDigits:
                    2,
            }
        )
            } MB`;
    }


    function formatDuration(
        milliseconds
    ) {

        if (
            milliseconds === null
            || milliseconds === undefined
        ) {
            return '—';
        }


        if (milliseconds < 1000) {

            return `${milliseconds} ms`;
        }


        return `${(
            milliseconds / 1000
        ).toLocaleString(
            'pt-BR',
            {
                minimumFractionDigits:
                    1,

                maximumFractionDigits:
                    2,
            }
        )
            } s`;
    }


    function labelStatus(status) {

        return (
            statusLabels[status]
            || status
            || '—'
        );
    }


    function labelType(type) {

        return (
            typeLabels[type]
            || type
            || '—'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    function statusClass(status) {

        if (
            status === 'CONCLUIDO'
        ) {
            return 'status-success';
        }


        if (
            status === 'FALHOU'
        ) {
            return 'status-danger';
        }


        if (
            status === 'CANCELADO'
        ) {
            return 'status-neutral';
        }


        if (
            processingStatuses.has(
                status
            )
        ) {
            return 'status-warning';
        }


        return 'status-primary';
    }


    function configureStatusBadge(
        element,
        status
    ) {

        element.className =
            `status-badge ${statusClass(status)}`;

        element.textContent =
            labelStatus(status);
    }


    function createStatusBadge(status) {

        const badge =
            document.createElement(
                'span'
            );


        configureStatusBadge(
            badge,
            status
        );


        return badge;
    }


    /*
    |--------------------------------------------------------------------------
    | Tabulator
    |--------------------------------------------------------------------------
    */

    const tableDependencies = {};


    if (window.XLSX) {

        tableDependencies.XLSX =
            window.XLSX;
    }


    if (
        window.jspdf
        && window.jspdf.jsPDF
    ) {

        tableDependencies.jspdf =
            window.jspdf.jsPDF;
    }


    const historyTable =
        new Tabulator(
            '#history-table',
            {

                data:
                    [],


                dependencies:
                    tableDependencies,


                layout:
                    'fitColumns',


                responsiveLayout:
                    'collapse',


                pagination:
                    true,


                paginationMode:
                    'local',


                paginationSize:
                    20,


                paginationSizeSelector:
                    [
                        10,
                        20,
                        50,
                        100,
                    ],


                paginationCounter:
                    'rows',


                placeholder:
                    'Nenhuma execução encontrada.',


                downloadRowRange:
                    'active',


                columnDefaults: {

                    headerSort:
                        true,

                    resizable:
                        true,
                },


                columns: [

                    {
                        title:
                            'Movimento',

                        field:
                            'data_movimento',

                        width:
                            125,

                        formatter(
                            cell
                        ) {
                            return formatMovementDate(
                                cell.getValue()
                            );
                        },

                        accessorDownload(
                            value
                        ) {
                            return formatMovementDate(
                                value
                            );
                        },
                    },


                    {
                        title:
                            'Tipo',

                        field:
                            'tipo_execucao',

                        width:
                            205,

                        formatter(
                            cell
                        ) {
                            const row =
                                cell
                                    .getRow()
                                    .getData();

                            const label =
                                labelType(
                                    cell.getValue()
                                );

                            return row.execucao_origem_id
                                ? `${label} · origem #${row.execucao_origem_id}`
                                : label;
                        },

                        accessorDownload(
                            value,
                            data
                        ) {
                            const label =
                                labelType(
                                    value
                                );

                            return data.execucao_origem_id
                                ? `${label} - origem #${data.execucao_origem_id}`
                                : label;
                        },
                    },


                    {
                        title:
                            'Status',

                        field:
                            'status',

                        width:
                            180,

                        formatter(
                            cell
                        ) {
                            return createStatusBadge(
                                cell.getValue()
                            );
                        },

                        accessorDownload(
                            value
                        ) {
                            return labelStatus(
                                value
                            );
                        },
                    },


                    {
                        title:
                            'Arquivo',

                        field:
                            'arquivo_nome',

                        minWidth:
                            300,

                        widthGrow:
                            3,

                        tooltip:
                            true,
                    },


                    {
                        title:
                            'Registros',

                        field:
                            'quantidade_registros',

                        width:
                            125,

                        hozAlign:
                            'right',

                        sorter:
                            'number',

                        formatter(
                            cell
                        ) {
                            return formatNumber(
                                cell.getValue()
                            );
                        },
                    },


                    {
                        title:
                            'Tempo',

                        field:
                            'duracao_milisegundos',

                        width:
                            115,

                        hozAlign:
                            'right',

                        sorter:
                            'number',

                        formatter(
                            cell
                        ) {
                            return formatDuration(
                                cell.getValue()
                            );
                        },

                        accessorDownload(
                            value
                        ) {
                            return formatDuration(
                                value
                            );
                        },
                    },


                    {
                        title:
                            'Executado em',

                        field:
                            'iniciado_em',

                        width:
                            190,

                        formatter(
                            cell
                        ) {
                            return formatDateTime(
                                cell.getValue()
                            );
                        },

                        accessorDownload(
                            value
                        ) {
                            return formatDateTime(
                                value
                            );
                        },
                    },
                ],
            }
        );


    historyTable.on(
        'rowClick',
        (
            event,
            row
        ) => {

            const data =
                row.getData();


            openDetail(
                data.id
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Resumo
    |--------------------------------------------------------------------------
    */

    async function loadSummary() {

        const data =
            await request(
                api.resumo
            );


        const last =
            data.ultima_execucao;


        if (last) {

            elements
                .lastMovementDate
                .textContent =
                formatMovementDate(
                    last.data_movimento
                );


            configureStatusBadge(
                elements.lastMovementStatus,
                last.status
            );


            elements
                .lastFileName
                .textContent =
                last.arquivo_nome
                || '—';


            elements
                .lastFileName
                .title =
                last.arquivo_nome
                || '';


            elements
                .lastRecordCount
                .textContent =
                formatNumber(
                    last.quantidade_registros
                );


            elements
                .lastDuration
                .textContent =
                formatDuration(
                    last.duracao_milisegundos
                );

        } else {

            elements
                .lastMovementDate
                .textContent =
                'Nenhuma execução';


            configureStatusBadge(
                elements.lastMovementStatus,
                null
            );


            elements
                .lastFileName
                .textContent =
                '—';


            elements
                .lastRecordCount
                .textContent =
                '—';


            elements
                .lastDuration
                .textContent =
                '—';
        }


        elements
            .runningCount
            .textContent =
            formatNumber(
                data.execucoes_em_andamento
            );


        elements
            .failureCount
            .textContent =
            formatNumber(
                data.falhas_ultimos_sete_dias
            );


        const lastSuccess =
            data.ultima_execucao_concluida;


        if (lastSuccess) {

            elements
                .lastSuccessDate
                .textContent =
                formatMovementDate(
                    lastSuccess.data_movimento
                );


            elements
                .lastSuccessTime
                .textContent =
                lastSuccess.concluido_em
                    ? (
                        'Concluído em '
                        + formatDateTime(
                            lastSuccess.concluido_em
                        )
                    )
                    : 'Concluído';

        } else {

            elements
                .lastSuccessDate
                .textContent =
                '—';


            elements
                .lastSuccessTime
                .textContent =
                'Nenhum envio concluído';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Histórico
    |--------------------------------------------------------------------------
    */
    let historySignature = null;

    async function loadHistory() {

        const data =
            await request(
                api.exportacoes
            );


        const newSignature =
            data
                .map(
                    item => (
                        `${item.id}:`
                        + `${item.status}:`
                        + `${item.atualizado_em ?? ''}`
                    )
                )
                .join('|');


        /*
         * Nenhuma alteração.
         *
         * Não fazemos o Tabulator
         * renderizar tudo novamente.
         */
        if (
            newSignature
            === historySignature
        ) {
            return;
        }


        historySignature =
            newSignature;


        let currentPage =
            historyTable.getPage();


        if (
            !Number.isInteger(
                currentPage
            )
        ) {
            currentPage =
                1;
        }


        await historyTable.replaceData(
            data
        );


        const maxPage =
            historyTable.getPageMax();


        if (
            currentPage > 1
            && currentPage <= maxPage
        ) {
            await historyTable.setPage(
                currentPage
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Downloads
    |--------------------------------------------------------------------------
    */

    function createDownloadFilename(
        extension
    ) {

        const now =
            new Date();


        const year =
            now.getFullYear();


        const month =
            String(
                now.getMonth() + 1
            ).padStart(
                2,
                '0'
            );


        const day =
            String(
                now.getDate()
            ).padStart(
                2,
                '0'
            );


        return (
            'nielseniq-exportacoes-'
            + `${year}-${month}-${day}`
            + `.${extension}`
        );
    }


    elements
        .exportExcel
        .addEventListener(
            'click',
            () => {

                historyTable.download(
                    'xlsx',

                    createDownloadFilename(
                        'xlsx'
                    ),

                    {
                        sheetName:
                            'Exportacoes',
                    }
                );
            }
        );


    elements
        .exportPdf
        .addEventListener(
            'click',
            () => {

                historyTable.download(
                    'pdf',

                    createDownloadFilename(
                        'pdf'
                    ),

                    {
                        orientation:
                            'landscape',

                        title:
                            'Historico de exportacoes NielsenIQ',

                        autoTable: {

                            styles: {
                                fontSize:
                                    7,
                            },

                            margin: {
                                top:
                                    18,
                            },
                        },
                    }
                );
            }
        );


    /*
    |--------------------------------------------------------------------------
    | Detalhe
    |--------------------------------------------------------------------------
    */
    let detailLoadingId = null;
    let currentDetailId = null;
    let currentDetailData = null;
    let pendingAction = null;
    let actionRunning = false;

    async function openDetail(id) {

        if (
            detailLoadingId === id
            && elements.dialog.open
        ) {
            return;
        }

        detailLoadingId =
            id;

        currentDetailId =
            id;

        currentDetailData =
            null;

        hideActionFeedback();

        elements
            .detailLoading
            .hidden =
            false;


        elements
            .detailContent
            .hidden =
            true;


        elements
            .detailLoading
            .textContent =
            'Carregando...';


        elements
            .detailTitle
            .textContent =
            `Execução #${id}`;


        if (
            !elements.dialog.open
        ) {

            elements.dialog.showModal();
        }


        try {

            const url =
                api.detalhe.replace(
                    '{id}',
                    String(id)
                );


            const data =
                await request(url);


            currentDetailData =
                data;


            renderDetail(
                data
            );


            elements
                .detailLoading
                .hidden =
                true;


            elements
                .detailContent
                .hidden =
                false;

        } catch (error) {

            elements
                .detailLoading
                .textContent =
                error.message
                || 'Não foi possível carregar os detalhes.';

        } finally {

            detailLoadingId =
                null;
        }
    }


    function renderDetail(data) {

        elements
            .detailMovement
            .textContent =
            formatMovementDate(
                data.data_movimento
            );


        elements
            .detailType
            .textContent =
            labelType(
                data.tipo_execucao
            );


        elements
            .detailStatus
            .textContent =
            labelStatus(
                data.status
            );


        elements
            .detailRecords
            .textContent =
            formatNumber(
                data.quantidade_registros
            );


        elements
            .detailSize
            .textContent =
            formatBytes(
                data.tamanho_bytes
            );


        elements
            .detailDuration
            .textContent =
            formatDuration(
                data.duracao_milisegundos
            );


        elements
            .detailFile
            .textContent =
            data.arquivo_nome
            || '—';


        elements
            .detailRemotePath
            .textContent =
            data.caminho_remoto
            || '—';


        elements
            .detailSha
            .textContent =
            data.sha256
            || '—';


        if (
            data.erro_mensagem
        ) {

            elements
                .detailErrorSection
                .hidden =
                false;


            elements
                .detailError
                .textContent =
                data.erro_mensagem;

        } else {

            elements
                .detailErrorSection
                .hidden =
                true;


            elements
                .detailError
                .textContent =
                '';
        }


        renderComparison(
            data.comparacao_origem
        );


        renderActions(
            data
        );


        renderTimeline(
            data.eventos
            || []
        );
    }


    function renderComparison(comparison) {

        if (!comparison) {

            elements
                .detailComparisonSection
                .hidden =
                true;

            return;
        }


        elements
            .detailComparisonSection
            .hidden =
            false;


        elements
            .detailOriginId
            .textContent =
            `Origem #${comparison.execucao_origem_id}`;


        elements
            .detailOriginRecords
            .textContent =
            formatNumber(
                comparison.quantidade_registros_origem
            );


        elements
            .detailCurrentRecords
            .textContent =
            formatNumber(
                comparison.quantidade_registros_atual
            );


        elements
            .detailOriginSize
            .textContent =
            formatBytes(
                comparison.tamanho_bytes_origem
            );


        elements
            .detailCurrentSize
            .textContent =
            formatBytes(
                comparison.tamanho_bytes_atual
            );


        elements
            .detailRecordDifference
            .textContent =
            formatSignedNumber(
                comparison.diferenca_registros
            );


        elements
            .detailOriginSha
            .textContent =
            comparison.sha256_origem
            || '—';


        if (
            comparison.conteudo_alterado
            === true
        ) {

            elements
                .detailContentChanged
                .textContent =
                'Alterado';

            elements
                .detailContentChanged
                .className =
                'status-badge status-warning';

        } else if (
            comparison.conteudo_alterado
            === false
        ) {

            elements
                .detailContentChanged
                .textContent =
                'Idêntico';

            elements
                .detailContentChanged
                .className =
                'status-badge status-success';

        } else {

            elements
                .detailContentChanged
                .textContent =
                'Indisponível';

            elements
                .detailContentChanged
                .className =
                'status-badge status-neutral';
        }
    }


    function renderActions(data) {

        const concluded =
            data.status
            === 'CONCLUIDO';


        elements
            .detailActionsSection
            .hidden =
            !concluded;


        if (!concluded) {
            return;
        }


        elements
            .detailResend
            .disabled =
            data.pode_reenviar
            !== true
            || actionRunning;


        elements
            .detailReprocess
            .disabled =
            data.pode_reprocessar
            !== true
            || actionRunning;


        if (
            data.pode_reenviar === true
            && data.pode_reprocessar === true
        ) {

            elements
                .detailActionsHelp
                .textContent =
                'Reenviar usa exatamente o arquivo já gerado. Reprocessar consulta novamente o ERP, gera um novo arquivo e tenta um novo envio.';

            return;
        }


        if (
            data.pode_reprocessar === true
            && data.arquivo_local_disponivel !== true
        ) {

            elements
                .detailActionsHelp
                .textContent =
                'O arquivo local original não está disponível para reenvio, mas o movimento ainda pode ser consultado novamente no ERP e reprocessado.';

            return;
        }


        elements
            .detailActionsHelp
            .textContent =
            'As ações manuais não estão disponíveis para esta execução.';
    }


    function hideActionFeedback() {

        elements
            .detailActionFeedback
            .hidden =
            true;

        elements
            .detailActionFeedback
            .textContent =
            '';
    }


    function showActionFeedback(
        message,
        type = 'success'
    ) {

        elements
            .detailActionFeedback
            .hidden =
            false;

        elements
            .detailActionFeedback
            .textContent =
            message;

        elements
            .detailActionFeedback
            .className =
            type === 'error'
                ? 'action-feedback action-feedback-error'
                : 'action-feedback action-feedback-success';
    }


    function openActionConfirmation(type) {

        if (
            currentDetailId === null
            || !currentDetailData
            || actionRunning
        ) {
            return;
        }


        const movement =
            formatMovementDate(
                currentDetailData.data_movimento
            );


        pendingAction = {
            type,
            id:
                currentDetailId,
        };


        if (type === 'reprocessar') {

            elements
                .actionConfirmTitle
                .textContent =
                `Reprocessar movimento de ${movement}?`;

            elements
                .actionConfirmMessage
                .textContent =
                'As vendas serão consultadas novamente no ERP. Um novo arquivo será gerado, comparado com a execução de origem e enviado ao SFTP, mesmo que o conteúdo continue idêntico. A execução original será preservada e nenhum arquivo remoto existente será sobrescrito.';

            elements
                .actionConfirmSubmit
                .textContent =
                'Reprocessar e reenviar';

        } else {

            elements
                .actionConfirmTitle
                .textContent =
                `Reenviar arquivo de ${movement}?`;

            elements
                .actionConfirmMessage
                .textContent =
                'O ERP não será consultado novamente. O arquivo local será validado por tamanho e SHA-256 e o mesmo conteúdo será enviado ao SFTP. Nenhum arquivo remoto existente será sobrescrito.';

            elements
                .actionConfirmSubmit
                .textContent =
                'Reenviar arquivo';
        }


        elements
            .actionConfirmDialog
            .showModal();
    }


    function setActionRunning(running) {

        actionRunning =
            running;


        elements
            .actionConfirmCancel
            .disabled =
            running;


        elements
            .actionConfirmSubmit
            .disabled =
            running;


        if (currentDetailData) {
            renderActions(
                currentDetailData
            );
        }
    }


    async function executePendingAction() {

        if (
            !pendingAction
            || actionRunning
        ) {
            return;
        }


        const action =
            pendingAction;


        const template =
            action.type === 'reprocessar'
                ? api.reprocessar
                : api.reenviar;


        const url =
            template.replace(
                '{id}',
                String(action.id)
            );


        const originalButtonText =
            elements
                .actionConfirmSubmit
                .textContent;


        setActionRunning(
            true
        );


        elements
            .actionConfirmSubmit
            .textContent =
            action.type === 'reprocessar'
                ? 'Reprocessando...'
                : 'Reenviando...';


        hideActionFeedback();


        try {

            const result =
                await request(
                    url,
                    {
                        method:
                            'POST',
                    }
                );


            elements
                .actionConfirmDialog
                .close();


            historySignature =
                null;


            await refreshDashboard();


            await openDetail(
                result.execucao_id
            );


            showActionFeedback(
                action.type === 'reprocessar'
                    ? 'Reprocessamento concluído e novo envio confirmado.'
                    : 'Reenvio concluído e confirmado.'
            );

        } catch (error) {

            elements
                .actionConfirmDialog
                .close();


            showActionFeedback(
                error.message
                || 'Não foi possível concluir a operação.',
                'error'
            );


            historySignature =
                null;


            try {
                await refreshDashboard();
            } catch {
            }

        } finally {

            pendingAction =
                null;


            elements
                .actionConfirmSubmit
                .textContent =
                originalButtonText;


            setActionRunning(
                false
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Timeline
    |--------------------------------------------------------------------------
    */

    function renderTimeline(events) {

        elements
            .detailTimeline
            .replaceChildren();


        if (!events.length) {

            const empty =
                document.createElement(
                    'div'
                );


            empty.className =
                'text-xs text-slate-400';


            empty.textContent =
                'Nenhum evento registrado.';


            elements
                .detailTimeline
                .appendChild(
                    empty
                );


            return;
        }


        events.forEach(
            (
                event,
                index
            ) => {

                const item =
                    document.createElement(
                        'div'
                    );


                item.className =
                    'timeline-item';


                const dot =
                    document.createElement(
                        'span'
                    );


                dot.className =
                    'timeline-dot';


                item.appendChild(
                    dot
                );


                if (
                    index
                    < events.length - 1
                ) {

                    const line =
                        document.createElement(
                            'span'
                        );


                    line.className =
                        'timeline-line';


                    item.appendChild(
                        line
                    );
                }


                const header =
                    document.createElement(
                        'div'
                    );


                header.className =
                    'timeline-header';


                const status =
                    document.createElement(
                        'span'
                    );


                status.className =
                    'timeline-status';


                status.textContent =
                    labelStatus(
                        event.status
                    );


                const time =
                    document.createElement(
                        'span'
                    );


                time.className =
                    'timeline-time';


                time.textContent =
                    formatTime(
                        event.ocorrido_em
                    );


                header.append(
                    status,
                    time
                );


                item.appendChild(
                    header
                );


                if (
                    event.mensagem
                ) {

                    const message =
                        document.createElement(
                            'p'
                        );


                    message.className =
                        'timeline-message';


                    message.textContent =
                        event.mensagem;


                    item.appendChild(
                        message
                    );
                }


                elements
                    .detailTimeline
                    .appendChild(
                        item
                    );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Monitor
    |--------------------------------------------------------------------------
    */

    function setOnline() {

        elements
            .connectionDot
            .className =
            'size-2 rounded-full bg-emerald-500 ring-4 ring-emerald-500/10';


        elements
            .connectionText
            .textContent =
            'Monitor online';
    }


    function setOffline() {

        elements
            .connectionDot
            .className =
            'size-2 rounded-full bg-red-500 ring-4 ring-red-500/10';


        elements
            .connectionText
            .textContent =
            'Falha na atualização';
    }


    function updateTimestamp() {

        elements
            .lastDashboardUpdate
            .textContent =
            (
                'Atualizado em '
                + new Intl.DateTimeFormat(
                    'pt-BR',
                    {
                        hour:
                            '2-digit',

                        minute:
                            '2-digit',

                        second:
                            '2-digit',
                    }
                ).format(
                    new Date()
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Refresh
    |--------------------------------------------------------------------------
    */

    let refreshing =
        false;


    async function refreshDashboard() {

        if (refreshing) {
            return;
        }


        refreshing =
            true;


        try {

            await Promise.all([
                loadSummary(),
                loadHistory(),
            ]);


            setOnline();

            updateTimestamp();

        } catch (error) {

            console.error(
                'Falha ao atualizar dashboard:',
                error
            );


            setOffline();

        } finally {

            refreshing =
                false;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Dialog
    |--------------------------------------------------------------------------
    */

    elements
        .detailResend
        .addEventListener(
            'click',
            () => {
                openActionConfirmation(
                    'reenviar'
                );
            }
        );


    elements
        .detailReprocess
        .addEventListener(
            'click',
            () => {
                openActionConfirmation(
                    'reprocessar'
                );
            }
        );


    elements
        .actionConfirmCancel
        .addEventListener(
            'click',
            () => {

                if (actionRunning) {
                    return;
                }

                pendingAction =
                    null;

                elements
                    .actionConfirmDialog
                    .close();
            }
        );


    elements
        .actionConfirmSubmit
        .addEventListener(
            'click',
            executePendingAction
        );


    elements
        .actionConfirmDialog
        .addEventListener(
            'cancel',
            (event) => {

                if (actionRunning) {
                    event.preventDefault();
                    return;
                }

                pendingAction =
                    null;
            }
        );


    elements
        .actionConfirmDialog
        .addEventListener(
            'click',
            (event) => {

                if (
                    !actionRunning
                    && event.target
                    === elements.actionConfirmDialog
                ) {

                    pendingAction =
                        null;

                    elements
                        .actionConfirmDialog
                        .close();
                }
            }
        );


    elements
        .detailClose
        .addEventListener(
            'click',
            () => {

                elements
                    .dialog
                    .close();
            }
        );


    elements
        .dialog
        .addEventListener(
            'click',
            (event) => {

                if (
                    event.target
                    === elements.dialog
                ) {

                    elements
                        .dialog
                        .close();
                }
            }
        );


    elements
        .dialog
        .addEventListener(
            'close',
            () => {

                currentDetailId =
                    null;

                currentDetailData =
                    null;

                detailLoadingId =
                    null;

                if (
                    elements.actionConfirmDialog.open
                    && !actionRunning
                ) {

                    pendingAction =
                        null;

                    elements
                        .actionConfirmDialog
                        .close();
                }
            }
        );


    /*
    |--------------------------------------------------------------------------
    | Inicialização
    |--------------------------------------------------------------------------
    */

    refreshDashboard();


    window.setInterval(
        refreshDashboard,

        Math.max(
            refreshInterval,
            5000
        )
    );

})();