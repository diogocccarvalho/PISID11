1. O Simulador (mazerun.exe)

    Responsabilidade: Gerar movimentos de Marsamis, calcular ruído e temperatura ambiente.

    Interface de Saída (MQTT PUB):

        pisid_mazesound_n: Publica níveis de ruído .

        pisid_mazetemp_n: Publica níveis de temperatura .

        pisid_mazemov_n: Publica eventos de movimento (Origem, Destino, ID) .

    Interface de Entrada (MQTT SUB):

        pisid_mazeact: Escuta comandos de atuadores (score, abrir/fechar portas) .

2. Motor de Ingestão e Lógica (Backend 1 - Python/Java)

    Responsabilidade: * Ouvir os sensores MQTT e persistir os dados brutos instantaneamente na BD Não-Relacional .

        Monitorizar limites de temperatura/ruído e acionar atuadores (ex: fechar portas, ligar AC) para evitar o fecho automático e fim da simulação.

        Detetar o gatilho de pontos: quando o número de Marsamis Odd é igual aos Even numa sala num determinado instante.

    Interface de Entrada: Subscrição dos tópicos MQTT dos sensores .

    Interface de Saída A: Publicação de comandos JSON no tópico MQTT pisid_mazeact.

    Interface de Saída B: Escrita direta (Inserts) de JSONs na base de dados MongoDB.

3. Base de Dados de Ingestão (MongoDB)

O buffer de alta velocidade para os dados empíricos.

    Responsabilidade: Armazenar de forma rápida e flexível (modelo não relacional) todas as leituras de sensores geradas a cada segundo.

    Interface: Recebe escritas do Motor de Ingestão e serve de fonte de leitura para o Migrador de Dados.

4. Pipeline de Migração (Backend 2 - Python/Java/PHP)

O "canalizador" do sistema, focado na tolerância a falhas.

    Responsabilidade:
    
        Realizar a migração incremental de dados do MongoDB para o MySQL em tempo real.

        Deteção de outliers e limpeza de dados "sujos" provenientes dos sensores antes de os gravar.

        Permitir reinicialização manual/automática pelo administrador em caso de crash para garantir que não há perda de dados.

    Interface: Leitura do MongoDB -> Processamento -> Escrita no MySQL.

5. Base de Dados Central (MySQL)

O "Save File" estruturado e final.

    Responsabilidade: Guardar a configuração inicial da simulação, estado atualizado das salas (OcupaçaoLabirinto) e o histórico limpo das medições (Mensagens, Temperatura, Som).

    Estrutura Core (Abstrata): Relacionamentos entre Equipas, Utilizadores, Simulações e Leituras .

    Interface: Escrita pelo Setup Web e pelo Pipeline de Migração. Leitura Read-Only consumida pela Aplicação Android .

6. Portal de Setup e Gestão (Frontend Web - HTML/PHP)

O painel de controlo humano da equipa.

    Responsabilidade: Fazer login de utilizadores, criar/editar configurações iniciais de simulações e iniciar a execução (arranque do mazerun.exe ou processos base) .

    Interface de Entrada: Formulários HTML introduzidos pelo utilizador.

    Interface de Saída: Escrita dos parâmetros na base de dados MySQL (ex: SetupMaze) através de Stored Procedures ou chamadas diretas.

7. Aplicação Mobile (Dashboard Android) --- Implementar um API point específico que devolve tudo o necessário para o frontend em vez de vários acessos.

A interface gráfica em tempo real para monitorização passiva.

    Responsabilidade: * Apresentar o gráfico de barras com a evolução do número de Marsamis por sala.

        Apresentar gráficos de linhas com a evolução temporal da temperatura e do ruído.

        Receber e mostrar alertas visuais em caso de perigo (excesso de temperatura/ruído).

    Regra de Ouro: Componente estritamente de leitura. Não contém lógica de pontuação nem atua no labirinto.

    Interface (API / Leitura de BD): Conecta-se diretamente ao MySQL ou a uma API intermediária para consumir as tabelas: OcupaçaoLabirinto, Mensagens, Temperatura e Som .
