-- Criar tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criar tabela de projetos
CREATE TABLE IF NOT EXISTS projetos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    criador_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criador_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Criar tabela de tarefas
CREATE TABLE IF NOT EXISTS tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    prazo_conclusao DATE NULL,
    designado_para INT NULL,
    comprovante VARCHAR(255),
    concluida TINYINT(1) DEFAULT 0,
    concluida_por INT NULL,
    concluida_em TIMESTAMP NULL,
    projeto_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE,
    FOREIGN KEY (concluida_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (designado_para) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Criar tabela intermediária usuarios_projetos (muitos-para-muitos)
CREATE TABLE IF NOT EXISTS usuarios_projetos (
    usuario_id INT NOT NULL,
    projeto_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, projeto_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE
);

-- Criar tabela de notas (opcional, vinculada a projetos)
CREATE TABLE IF NOT EXISTS notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255),
    conteudo TEXT,
    projeto_id INT NULL,
    usuario_id INT NOT NULL,
    categoria VARCHAR(100) DEFAULT 'Geral',
    prioridade ENUM('alta', 'media', 'baixa') DEFAULT 'media',
    cor VARCHAR(7) DEFAULT '#ffffff',
    tipo ENUM('texto', 'lista', 'codigo') DEFAULT 'texto',
    concluida TINYINT(1) DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de conexões entre usuários
CREATE TABLE IF NOT EXISTS usuarios_conexoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_conectado INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_conexao_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_conexao_conectado FOREIGN KEY (id_conectado) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela convite de usuários
CREATE TABLE IF NOT EXISTS convites_compartilhamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    remetente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    status ENUM('pendente', 'aceito', 'recusado') DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aceito_em TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE,
    FOREIGN KEY (remetente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Criar índices para melhor performance
CREATE INDEX idx_tarefas_projeto_id ON tarefas(projeto_id);
CREATE INDEX idx_tarefas_concluida ON tarefas(concluida);
CREATE INDEX idx_tarefas_prazo ON tarefas(prazo_conclusao);
CREATE INDEX idx_tarefas_designado ON tarefas(designado_para);
CREATE INDEX idx_tarefas_concluida_em ON tarefas(concluida_em);
CREATE INDEX idx_usuarios_projetos_usuario ON usuarios_projetos(usuario_id);
CREATE INDEX idx_usuarios_projetos_projeto ON usuarios_projetos(projeto_id);
CREATE INDEX idx_usuarios_conexoes_usuario ON usuarios_conexoes(id_usuario);
CREATE INDEX idx_usuarios_conexoes_conectado ON usuarios_conexoes(id_conectado);