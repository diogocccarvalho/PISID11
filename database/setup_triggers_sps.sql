-- Triggers + SPs
-- ============================================================
-- IMPORTANTE: Correr este ficheiro depois do setup_mysql.sql
-- ============================================================

-- ============================================================
-- ALTERAÇÕES ÀS TABELAS
-- ============================================================

-- Adicionar limites de alerta à tabela Simulacao
-- (os triggers de alerta precisam de saber os limites definidos)
ALTER TABLE Simulacao ADD COLUMN IF NOT EXISTS limSom FLOAT DEFAULT NULL;
ALTER TABLE Simulacao ADD COLUMN IF NOT EXISTS limTemperaturaMax FLOAT DEFAULT NULL;
ALTER TABLE Simulacao ADD COLUMN IF NOT EXISTS limTemperaturaMin FLOAT DEFAULT NULL;

-- Corrigir dataHoraInicio para permitir NULL
-- (a data de início só é preenchida quando a simulação arranca,
--  não quando é criada)
ALTER TABLE Simulacao MODIFY COLUMN dataHoraInicio DATETIME DEFAULT NULL;


-- ============================================================
-- STORED PROCEDURES
-- ============================================================

-- ------------------------------------------------------------
-- SP 1. Inserir_Alerta
-- Insere um registo de alerta na tabela Mensagens.
-- Chamado pelos triggers trg_alerta_som e trg_alerta_temperatura.
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Inserir_Alerta$$
CREATE PROCEDURE Inserir_Alerta(
    IN p_hora DATETIME,
    IN p_horaEscreita DATETIME,
    IN p_mensagem VARCHAR(255),
    IN p_sala INT,
    IN p_sensor VARCHAR(255),
    IN p_leitura FLOAT,
    IN p_tipoALERTA VARCHAR(255)
)
BEGIN
    INSERT INTO Mensagens (hora, horaEscreita, mensagem, sala, sensor, leitura, tipoALERTA)
    VALUES (p_hora, p_horaEscreita, p_mensagem, p_sala, p_sensor, p_leitura, p_tipoALERTA);
END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 2. Criar_utilizador
-- Cria um novo utilizador e associa-o a uma equipa existente.
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Criar_utilizador$$
CREATE PROCEDURE Criar_utilizador(
    IN p_email VARCHAR(255),
    IN p_nome VARCHAR(255),
    IN p_telemovel VARCHAR(9),
    IN p_dataNascimento DATE,
    IN p_tipo VARCHAR(255),
    IN p_idEquipa INT
)
BEGIN
    IF EXISTS (SELECT 1 FROM Utilizador WHERE Email = p_email) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: já existe um utilizador com este email.';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Equipa WHERE idEquipa = p_idEquipa) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: a equipa indicada não existe.';
    END IF;

    INSERT INTO Utilizador (Email, Nome, Telemovel, dataNascimento, tipo, idEquipa)
    VALUES (p_email, p_nome, p_telemovel, p_dataNascimento, p_tipo, p_idEquipa);
END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 3. Remover_utilizador
-- Remove um utilizador da base de dados pelo seu email.
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Remover_utilizador$$
CREATE PROCEDURE Remover_utilizador(
    IN p_email VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM Utilizador WHERE Email = p_email) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: utilizador não encontrado.';
    END IF;

    DELETE FROM Utilizador WHERE Email = p_email;
END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 4. Alterar_utilizador
-- Permite alterar os dados pessoais de um utilizador.
-- Não altera PK (Email) nem FK (idEquipa).
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Alterar_utilizador$$
CREATE PROCEDURE Alterar_utilizador(
    IN p_email VARCHAR(255),
    IN p_nome VARCHAR(255),
    IN p_telemovel VARCHAR(9),
    IN p_dataNascimento DATE,
    IN p_tipo VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM Utilizador WHERE Email = p_email) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: utilizador não encontrado.';
    END IF;

    UPDATE Utilizador
    SET Nome = p_nome,
        Telemovel = p_telemovel,
        dataNascimento = p_dataNascimento,
        tipo = p_tipo
    WHERE Email = p_email;
END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 5. Criar_simulacao
-- Cria uma nova simulação com estado inicial 'pendente'.
-- Os limites de som e temperatura podem ser definidos aqui.
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Criar_simulacao$$
CREATE PROCEDURE Criar_simulacao(
    IN p_descricao VARCHAR(255),
    IN p_utilizadorCriador VARCHAR(255),
    IN p_limSom FLOAT,
    IN p_limTemperaturaMax FLOAT,
    IN p_limTemperaturaMin FLOAT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM Utilizador WHERE Email = p_utilizadorCriador) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: utilizador criador não encontrado.';
    END IF;

    INSERT INTO Simulacao (descricao, dataHoraInicio, dataHoraFim, estado, utilizador_criador, limSom, limTemperaturaMax, limTemperaturaMin)
    VALUES (p_descricao, NULL, NULL, 'pendente', p_utilizadorCriador, p_limSom, p_limTemperaturaMax, p_limTemperaturaMin);
END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 6. Alterar_simulacao
-- Permite alterar campos editáveis de uma simulação.
-- Só quem criou pode alterar e não pode estar em curso.
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Alterar_simulacao$$
CREATE PROCEDURE Alterar_simulacao(
    IN p_idSimulacao INT,
    IN p_descricao VARCHAR(255),
    IN p_utilizadorCriador VARCHAR(255),
    IN p_limSom FLOAT,
    IN p_limTemperaturaMax FLOAT,
    IN p_limTemperaturaMin FLOAT
)
BEGIN
    DECLARE v_criador VARCHAR(255);
    DECLARE v_estado VARCHAR(255);

    SELECT utilizador_criador, estado INTO v_criador, v_estado
    FROM Simulacao
    WHERE idSimulacao = p_idSimulacao;

    IF v_criador IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: simulação não encontrada.';
    END IF;

    IF v_criador != p_utilizadorCriador THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: apenas quem criou a simulação pode alterá-la.';
    END IF;

    IF v_estado = 'ativo' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: não é possível alterar uma simulação em curso.';
    END IF;

    UPDATE Simulacao
    SET descricao = p_descricao,
        limSom = p_limSom,
        limTemperaturaMax = p_limTemperaturaMax,
        limTemperaturaMin = p_limTemperaturaMin
    WHERE idSimulacao = p_idSimulacao;
END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 7. Iniciar_simulacao
-- Muda o estado para 'em curso' e regista data/hora de início.
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Iniciar_simulacao$$
CREATE PROCEDURE Iniciar_simulacao(
    IN p_idSimulacao INT
)
BEGIN
    DECLARE v_estado VARCHAR(255);

    SELECT estado INTO v_estado
    FROM Simulacao
    WHERE idSimulacao = p_idSimulacao;

    IF v_estado IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: simulação não encontrada.';
    END IF;

    IF v_estado = 'ativo' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: simulação já está em curso.';
    END IF;

    IF v_estado = 'terminada' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: simulação já foi terminada.';
    END IF;

    UPDATE Simulacao
    SET estado = 'ativo',
        dataHoraInicio = NOW()
    WHERE idSimulacao = p_idSimulacao;

    DELETE FROM OcupacaoLabirinto WHERE simulacao = p_idSimulacao;

    INSERT INTO OcupacaoLabirinto (sala, NumeroOdd, NumeroEven, num_gatilhos, simulacao) VALUES
    (1, 0, 0, 0, p_idSimulacao),
    (2, 0, 0, 0, p_idSimulacao),
    (3, 0, 0, 0, p_idSimulacao),
    (4, 0, 0, 0, p_idSimulacao),
    (5, 0, 0, 0, p_idSimulacao),
    (6, 0, 0, 0, p_idSimulacao),
    (7, 0, 0, 0, p_idSimulacao),
    (8, 0, 0, 0, p_idSimulacao),
    (9, 0, 0, 0, p_idSimulacao),
    (10, 0, 0, 0, p_idSimulacao);
END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 8. Terminar_simulacao
-- Muda o estado para 'terminada' e regista data/hora de fim.
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Terminar_simulacao$$
CREATE PROCEDURE Terminar_simulacao(
    IN p_idSimulacao INT
)
BEGIN
    DECLARE v_estado VARCHAR(255);

    SELECT estado INTO v_estado
    FROM Simulacao
    WHERE idSimulacao = p_idSimulacao;

    IF v_estado IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: simulação não encontrada.';
    END IF;

    IF v_estado = 'terminada' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: simulação já foi terminada.';
    END IF;

    UPDATE Simulacao
    SET estado = 'terminada',
        dataHoraFim = NOW()
    WHERE idSimulacao = p_idSimulacao;
END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 9. Reiniciar_migracao
-- Reinicia o processo de migração em caso de falha.
-- Usado exclusivamente pelo administrador.
-- Limpa dados migrados a meio e repõe simulação a 'pendente'.
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Reiniciar_migracao$$
CREATE PROCEDURE Reiniciar_migracao(
    IN p_idSimulacao INT
)
BEGIN
    DECLARE v_estado VARCHAR(255);

    SELECT estado INTO v_estado
    FROM Simulacao
    WHERE idSimulacao = p_idSimulacao;

    IF v_estado IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro: simulação não encontrada.';
    END IF;

    -- Limpar dados dos sensores desta simulação
    DELETE FROM Som WHERE idSimulacao = p_idSimulacao;
    DELETE FROM SomOutlier WHERE idSimulacao = p_idSimulacao;
    DELETE FROM Temperatura WHERE idSimulacao = p_idSimulacao;
    DELETE FROM TemperaturaOutlier WHERE idSimulacao = p_idSimulacao;
    DELETE FROM MedicoesPassagem WHERE simulacao = p_idSimulacao;

    -- Repor ocupação do labirinto a zeros
    UPDATE OcupacaoLabirinto
    SET NumeroOdd = 0,
        NumeroEven = 0,
        num_gatilhos = 0
    WHERE simulacao = p_idSimulacao;

    -- Apagar alertas gerados durante a migração falhada
    DELETE FROM Mensagens
    WHERE hora >= (
        SELECT dataHoraInicio FROM Simulacao WHERE idSimulacao = p_idSimulacao
    );

    -- Voltar simulação a pendente
    UPDATE Simulacao
    SET estado = 'pendente',
        dataHoraInicio = NULL,
        dataHoraFim = NULL
    WHERE idSimulacao = p_idSimulacao;

END$$
DELIMITER ;


-- ------------------------------------------------------------
-- SP 10. Verificar_Outlier
-- Verifica se um valor é outlier com base nos últimos 5 valores
-- usando Z-Score. Retorna 1 se outlier, 0 se normal.
-- Camada extra de segurança além do Python.
-- (Removido o anomalo = 0 porque a tabela principal só tem dados normais agora)
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS Verificar_Outlier$$
CREATE PROCEDURE Verificar_Outlier(
    IN p_valor FLOAT,
    IN p_tipoSensor VARCHAR(255),
    IN p_idSimulacao INT,
    OUT p_is_outlier INT
)
BEGIN
    DECLARE v_media FLOAT;
    DECLARE v_desvio FLOAT;
    DECLARE v_zscore FLOAT;

    SET p_is_outlier = 0;

    IF p_tipoSensor = 'Som' THEN
        SELECT AVG(som), STD(som) INTO v_media, v_desvio
        FROM (
            SELECT som FROM Som
            WHERE idSimulacao = p_idSimulacao
            ORDER BY hora DESC LIMIT 5
        ) AS ultimos;

    ELSEIF p_tipoSensor = 'Temperatura' THEN
        SELECT AVG(temperatura), STD(temperatura) INTO v_media, v_desvio
        FROM (
            SELECT temperatura FROM Temperatura
            WHERE idSimulacao = p_idSimulacao
            ORDER BY hora DESC LIMIT 5
        ) AS ultimos;
    END IF;

    IF v_media IS NOT NULL AND v_desvio IS NOT NULL AND v_desvio > 0 THEN
        SET v_zscore = ABS((p_valor - v_media) / v_desvio);
        IF v_zscore > 3 THEN
            SET p_is_outlier = 1;
        END IF;
    END IF;

END$$
DELIMITER ;


-- ============================================================
-- TRIGGERS
-- ============================================================
-- Nota: O trg_outlier_temperatura foi removido, uma vez que o script Python
-- agora divide automaticamente os dados na inserção pelas respetivas
-- tabelas (Temperatura ou TemperaturaOutlier).

-- ------------------------------------------------------------
-- TRIGGER 1. trg_atualizar_ocupacao
-- Depois de inserir na tabela MedicoesPassagem, atualiza a
-- contagem de marsamis odd/even na tabela OcupacaoLabirinto.
-- Decrementa na sala de origem e incrementa na sala de destino.
-- RoomOrigin = 0 -> largada inicial, não decrementa.
-- RoomDestiny = 0 -> marsami preso, não incrementa.
-- ------------------------------------------------------------
DELIMITER $$
DROP TRIGGER IF EXISTS trg_atualizar_ocupacao$$
CREATE TRIGGER trg_atualizar_ocupacao
AFTER INSERT ON MedicoesPassagem
FOR EACH ROW
BEGIN
    DECLARE v_tipo VARCHAR(4);

    -- Determinar se o marsami é odd ou even pelo número
    IF MOD(NEW.numeroMarsami, 2) = 1 THEN
        SET v_tipo = 'odd';
    ELSE
        SET v_tipo = 'even';
    END IF;

    -- Decrementar na sala de origem (se não for largada inicial)
    IF NEW.salaOrigem != 0 THEN
        IF v_tipo = 'odd' THEN
            UPDATE OcupacaoLabirinto
            SET NumeroOdd = NumeroOdd - 1
            WHERE sala = NEW.salaOrigem AND simulacao = NEW.simulacao AND NumeroOdd > 0;
        ELSE
            UPDATE OcupacaoLabirinto
            SET NumeroEven = NumeroEven - 1
            WHERE sala = NEW.salaOrigem AND simulacao = NEW.simulacao AND NumeroEven > 0;
        END IF;
    END IF;

    -- Incrementar na sala de destino (se marsami não estiver preso)
    IF NEW.salaDestino != 0 THEN
        IF v_tipo = 'odd' THEN
            UPDATE OcupacaoLabirinto
            SET NumeroOdd = NumeroOdd + 1
            WHERE sala = NEW.salaDestino AND simulacao = NEW.simulacao;
        ELSE
            UPDATE OcupacaoLabirinto
            SET NumeroEven = NumeroEven + 1
            WHERE sala = NEW.salaDestino AND simulacao = NEW.simulacao;
        END IF;
    END IF;

END$$
DELIMITER ;