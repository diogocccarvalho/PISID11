CREATE TABLE Equipa (
    idEquipa INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    PRIMARY KEY (idEquipa)
);

CREATE TABLE Utilizador (
    Email VARCHAR(255) NOT NULL,
    Nome VARCHAR(255) NOT NULL,
    Telemovel VARCHAR(9) NOT NULL,
    dataNascimento DATE NOT NULL,
    tipo VARCHAR(255) NOT NULL,
    idEquipa INT NOT NULL,
    PRIMARY KEY (Email),
    FOREIGN KEY (idEquipa) REFERENCES Equipa(idEquipa)
);

CREATE TABLE Simulacao (
    idSimulacao INT NOT NULL AUTO_INCREMENT,
    descricao VARCHAR(255) NOT NULL,
    dataHoraInicio DATETIME NOT NULL,
    dataHoraFim DATETIME,
    estado VARCHAR(255) NOT NULL,
    utilizador_criador VARCHAR(255) NOT NULL,
    PRIMARY KEY (idSimulacao),
    FOREIGN KEY (utilizador_criador) REFERENCES Utilizador(Email)
);

CREATE TABLE Sala (
    idSala INT NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (idSala)
);

CREATE TABLE Som (
    idSom INT NOT NULL AUTO_INCREMENT,
    hora DATETIME NOT NULL,
    som FLOAT NOT NULL,
    idSimulacao INT NOT NULL,
    PRIMARY KEY (idSom),
    FOREIGN KEY (idSimulacao) REFERENCES Simulacao(idSimulacao)
);

CREATE TABLE Temperatura (
    idTemperatura INT NOT NULL AUTO_INCREMENT,
    hora DATETIME NOT NULL,
    temperatura FLOAT NOT NULL,
    idSimulacao INT NOT NULL,
    PRIMARY KEY (idTemperatura),
    FOREIGN KEY (idSimulacao) REFERENCES Simulacao(idSimulacao)
);

CREATE TABLE Mensagens (
    idMensagem INT AUTO_INCREMENT,
    hora DATETIME,
    horaEscreita DATETIME,
    mensagem VARCHAR(255),
    sala INT,
    sensor VARCHAR(255),
    leitura FLOAT,
    tipoALERTA VARCHAR(255),
    PRIMARY KEY (idMensagem),
    FOREIGN KEY (sala) REFERENCES Sala(idSala)
);

CREATE TABLE OcupacaoLabirinto (
    idOcupacao INT NOT NULL AUTO_INCREMENT,
    sala INT NOT NULL,
    NumeroOdd INT NOT NULL,
    NumeroEven INT NOT NULL,
    simulacao INT NOT NULL,
    num_gatulhos INT NOT NULL,
    PRIMARY KEY (idOcupacao),
    FOREIGN KEY (sala) REFERENCES Sala(idSala),
    FOREIGN KEY (simulacao) REFERENCES Simulacao(idSimulacao)
);

CREATE TABLE MedicoesPassagem (
    idMedicao INT NOT NULL AUTO_INCREMENT,
    numeroMarsami INT NOT NULL,
    salaOrigem INT NOT NULL,
    salaDestino INT NOT NULL,
    status VARCHAR(255) NOT NULL,
    hora DATETIME NOT NULL,
    equipa INT NOT NULL,
    simulacao INT NOT NULL,
    PRIMARY KEY (idMedicao),
    FOREIGN KEY (salaOrigem) REFERENCES Sala(idSala),
    FOREIGN KEY (salaDestino) REFERENCES Sala(idSala),
    FOREIGN KEY (equipa) REFERENCES Equipa(idEquipa),
    FOREIGN KEY (simulacao) REFERENCES Simulacao(idSimulacao)
);
