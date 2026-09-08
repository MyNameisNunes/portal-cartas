# Decisões de UX e produto

O público do portal são administradores com familiaridade muito variada com
tecnologia. Cada decisão abaixo traz o problema que ela resolve, não só a
descrição do que foi feito.

## 1. Nenhuma ação assíncrona acontece em silêncio

**Problema.** O `<select>` de Edição depende de uma requisição disparada pela
escolha do Card Game. Se ele simplesmente ficar vazio por meio segundo, o usuário
não tem como saber se o sistema travou, se ele pulou um passo, ou se precisa
clicar de novo — e a reação natural é clicar de novo.

**Solução.** Quatro estados visíveis e mutuamente exclusivos:

| Estado | Aparência |
| --- | --- |
| Inicial | Desabilitado, *"Selecione o card game primeiro"* |
| Carregando | Desabilitado, *"Carregando edições…"* e spinner sobreposto |
| Pronto | Habilitado, populado com as edições do jogo |
| Erro | Borda vermelha e botão *"Tentar carregar as edições novamente"* |

O estado inicial ensina a ordem de preenchimento sem precisar de texto de ajuda.
O estado de erro oferece uma saída, em vez de deixar o formulário travado.

A mesma regra vale para os botões de envio: durante a requisição eles ficam
desabilitados, com `aria-busy` e rótulo trocado (*"Salvando…"*, *"Entrando…"*,
*"Excluindo…"*). O clique duplo — origem mais comum de registro duplicado em
formulário administrativo — deixa de ser possível, sem depender de o usuário
perceber que algo está em andamento.

**Detalhes que vieram junto.** Trocar de Card Game rapidamente aborta a
requisição anterior (`AbortController`), então uma resposta atrasada do jogo
antigo não sobrescreve a lista do jogo escolhido por último. E as edições já
buscadas ficam em cache, para voltar a um jogo anterior ser instantâneo.

## 2. Exclusão exige confirmação nomeada

**Problema.** *Excluir* fica a poucos pixels de *Editar* dentro de cada card, e a
operação é irreversível. O `confirm()` nativo do navegador não ajuda: ele é
genérico, não diz **qual** carta será apagada, e os botões *OK/Cancelar* são
dispensados no automático por quem usa o sistema todo dia.

**Solução.** Um `role="alertdialog"` que nomeia a carta, avisa em destaque que a
ação não pode ser desfeita, e rotula os botões pela consequência: *"Manter
carta"* e *"Sim, excluir"*. O foco vai para o diálogo, `Esc` cancela, e o botão
destrutivo é o único elemento vermelho na tela naquele momento.

## 3. Grid de cards em vez de tabela

**Problema.** Carta de TCG é reconhecida pela arte, não pelo texto. Uma tabela
obriga o administrador a ler nomes em inglês para localizar visualmente um item
que ele já reconheceria de relance.

**Solução.** Grid de cards com a imagem em destaque e a raridade como badge
colorido sobre ela. Além de casar com o modelo mental do usuário, o grid usa CSS
Grid e reflui de 5 → 2 → 1 coluna conforme a largura. Uma tabela com 6 colunas
exigiria rolagem horizontal no celular, que é justamente onde a conferência
rápida de acervo acontece.

## 4. Validação que aponta o campo, não só a tela

**Problema.** "Erro ao salvar" no topo do formulário obriga o usuário a caçar o
que está errado.

**Solução.** O backend devolve um mapa `campos` no erro 422, e o frontend usa
isso para marcar cada input com `aria-invalid`, escrever a mensagem específica
logo abaixo dele e focar o primeiro problema. A mensagem some assim que o campo é
corrigido. Campos opcionais são rotulados como *(opcional)* — em vez de marcar
os obrigatórios e deixar o resto por dedução.

## 5. Mensagens de erro escritas para pessoas

Nenhuma exceção de banco ou stack trace chega à tela. Cada falha vira uma frase
que descreve o que aconteceu e o que fazer: *"Essa edição não pertence ao card
game selecionado."*, *"A imagem deve ter no máximo 5 MB."*, *"Sessão expirada.
Faça login novamente para continuar."*.

A exceção deliberada é o login: quando o e-mail ou a senha não conferem, a
mensagem é a mesma nos dois casos. Ser específico ali entregaria a um atacante
quais e-mails existem no sistema.

## 6. Tema claro e escuro, sem piscada e sem cor solta

O portal é operado por administradores que passam o dia nele, inclusive à noite.
O tema escuro não é enfeite: é o que evita a tela branca de 1440px na cara de
quem confere acervo depois do expediente.

**Sem escolha salva, vale o tema do sistema.** Ao clicar no botão do topo, a
escolha vira explícita e passa a valer em todas as páginas — inclusive se o
sistema mudar depois. Quem escolheu manda; quem não escolheu é acompanhado.

**A troca não pisca.** O tema é aplicado por um script inline no `<head>`, antes
da primeira pintura. Um arquivo externo só carregaria depois do CSS, e a tela
apareceria clara por um quadro antes de escurecer — justamente o incômodo que o
tema escuro deveria evitar.

**Uma paleta, declarada num lugar só.** Toda cor sai de um token em `:root`; o
tema escuro redeclara os mesmos tokens. Os dois temas mantêm no mínimo 4.5:1 de
contraste no texto, no texto apagado e nas cores de estado.

**Cor não é o único sinal.** Com a marca em vermelho, *Excluir* em vermelho
ficaria idêntico a *Editar*. A diferença passou a ser de forma: a ação principal
vem preenchida, a destrutiva vem contornada. O mesmo princípio já valia para os
campos inválidos, que trazem ícone e texto além da borda vermelha.
