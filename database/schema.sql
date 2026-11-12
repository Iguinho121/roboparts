CREATE DATABASE IF NOT EXISTS flux;
USE flux;


CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('Comum', 'Admin') DEFAULT 'Comum',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
)

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  descricao TEXT
);

CREATE TABLE fornecedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(150),
  telefone VARCHAR(20),
  endereco VARCHAR(255)
);

CREATE TABLE condicoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL,
  descricao TEXT
);

CREATE TABLE projetos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    data_inicio DATE,
    data_fim DATE,
    status ENUM('Planejamento', 'Em andamento', 'Concluido') DEFAULT 'Planejamento',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE componentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    codigo VARCHAR(50) UNIQUE,
    categoria_id INT NOT NULL,
    fornecedor_id INT,
    condicao_id INT NOT NULL,
    quantidade_total INT NOT NULL DEFAULT 0,
    quantidade_disponivel INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 5,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
    FOREIGN KEY (condicao_id) REFERENCES condicoes(id) ON DELETE RESTRICT
);

CREATE TABLE projeto_componentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    componente_id INT NOT NULL,
    quantidade INT NOT NULL,
    
    FOREIGN KEY(projeto_id) REFERENCES projetos(id) ON DELETE CASCADE,
    FOREIGN KEY (componente_id) REFERENCES componentes(id) ON DELETE CASCADE,
    UNIQUE (projeto_id, componente_id)
);


CREATE TABLE movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    componente_id INT NOT NULL,
    projeto_id INT, 
    tipo_movimentacao ENUM('Entrada', 'Saida', 'Emprestimo', 'Devolucao') NOT NULL,
    quantidade INT NOT NULL,
    data_movimentacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    observacao TEXT,
    
    FOREIGN KEY (componente_id) REFERENCES componentes(id) ON DELETE CASCADE,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE SET NULL
);


CREATE TABLE kits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    tipo_projeto VARCHAR(100)
);

CREATE TABLE kit_componentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kit_id INT NOT NULL,
    componente_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    
    FOREIGN KEY (kit_id) REFERENCES kits(id) ON DELETE CASCADE,
    FOREIGN KEY (componente_id) REFERENCES componentes(id) ON DELETE CASCADE,
    UNIQUE (kit_id, componente_id)
);


-- Administrador
INSERT INTO usuarios (nome, email, senha, tipo_usuario) 
VALUES ('Administrador Geral', 'igor.s@roboparts.com', '123456', 'Admin');

-- Usuario comum
INSERT INTO usuarios (nome, email, senha, tipo_usuario) 
VALUES ('Aluno Teste', 'vitor@email.com', '123456', 'Comum');

INSERT INTO categorias (nome, descricao) VALUES 
('Microcontroladores', 'Placas de desenvolvimento e processamento central'),
('Sensores', 'Componentes para leitura do ambiente (luz, distância, temperatura)'),
('Atuadores', 'Motores, servos e componentes de movimento');

-- FORNECEDORES
INSERT INTO fornecedores (nome, email, telefone, endereco) VALUES 
('RoboCore', 'robo.core@gmail.com', '11910307554', 'Rua Ribeirão Preto, 100 - SP'),
('FilipeFlop', 'felipe.flop@gmail.com', '11964371606', 'Av. Prosperidade, 200 - SC');

INSERT INTO condicoes (nome, descricao) VALUES 
('Novo', 'Componente na embalagem original, nunca utilizado'),
('Usado - Bom estado', 'Componente já utilizado, mas funcionando perfeitamente'),
('Danificado', 'Componente com defeito ou precisando de reparo');

INSERT INTO projetos (nome, descricao, data_inicio, status) VALUES 
('Carrinho Seguidor de Linha', 'Robô autônomo que segue uma linha preta no chão', '2026-11-01', 'Em andamento'),
('Braço Robótico Industrial', 'Braço de 4 eixos para separação de peças', '2026-11-15', 'Planejamento');

INSERT INTO componentes (nome, descricao, codigo, categoria_id, fornecedor_id, condicao_id, quantidade_total, quantidade_disponivel, estoque_minimo) VALUES 
('Arduino Uno R3', 'Placa microcontroladora ATmega328P', 'ARD-001', 1, 1, 1, 10, 8, 3),
('Sensor Ultrassônico HC-SR04', 'Sensor de distância', 'SEN-001', 2, 2, 1, 15, 15, 5),
('Micro Servo 9g SG90', 'Servo motor pequeno para articulações', 'ATU-001', 3, 1, 2, 4, 4, 5); 
-- ESTOQUE ABAIXO DO MINIMO PARA TESTE, (TEM QUE APARECER MENSAGEM DE ALERTA CASO O ESTOQUE ESTEJA ABAIXO DO MINIMO)

INSERT INTO projeto_componentes (projeto_id, componente_id, quantidade) VALUES 
(1, 1, 1), -- carrinho usa 1 arduino
(1, 2, 2), -- carrinho usa 2 sensores ultrassonicos
(2, 1, 1), -- braço usa 1 arduino
(2, 3, 4); -- braco usa 4 servos


INSERT INTO movimentacoes (componente_id, projeto_id, tipo_movimentacao, quantidade, observacao) VALUES 
(1, NULL, 'Entrada', 10, 'Compra inicial de Arduinos'),
(1, 1, 'Emprestimo', 2, 'Arduinos separados para a equipe do Carrinho Seguidor de Linha');

INSERT INTO kits (nome, descricao, tipo_projeto) VALUES 
('Kit Iniciante Arduino', 'Pacote básico para quem está começando em robótica', 'Educacional');

INSERT INTO kit_componentes (kit_id, componente_id, quantidade) VALUES 
(1, 1, 1), -- 1 Arduino no Kit
(1, 2, 1), -- 1 Sensor no Kit
(1, 3, 1); -- 1 Servo no Kit
