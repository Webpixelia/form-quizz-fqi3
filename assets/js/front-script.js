let selectedLevel = "";
let currentQuestionIndex = 0;
let correctAnswers = 0;
let answerSelected = false;
let questions = [];
let totalQuestions = 0;
const siteUrl = window.location.href;
const loadingIndicator = document.getElementById('loading-indicator');
const PERCENTAGE_THRESHOLD = 80;

window.addEventListener('load', function() {
    sessionStorage.removeItem('incorrectAnswers');
});

function showLevelSelection() {
    document.getElementById('start-container').style.display = 'none';
    document.getElementById('level-container').style.display = 'block';
}

document.addEventListener("DOMContentLoaded", function () {
    // Gestion de l'affichage des options de minuterie
    const enableTimer = document.getElementById('enable-timer');
    const timerSettings = document.getElementById('timer-settings');
    const timerDuration = document.getElementById('timer-duration');

    if (enableTimer) {
        enableTimer.addEventListener('change', function() {
            if (this.checked) {
                timerSettings.style.display = 'block';
                timerDuration.disabled = false;
            } else {
                timerSettings.style.display = 'none';
                timerDuration.disabled = true;
            }
        });
    }

    // Gestion des niveaux
    const choiceMessage = document.getElementById('level-choice-message');
    const radioButtons = document.querySelectorAll('input[name="niveau"]');
    radioButtons.forEach(button => {
        button.addEventListener('change', function () {
            // Effacez le message d'erreur lorsque l'utilisateur fait un choix
            choiceMessage.innerText = "";
        });
    });
});

/*
* The startQuizWithLevel function initializes a quiz based on the selected level 
* and manages timer settings if enabled. 
* It updates the user interface accordingly and communicates with the server to save 
* timer settings and increment the quiz count.
*/
async function startQuizWithLevel(level) {
    const selectedLevel = document.querySelector('input[name="niveau"]:checked');
    const choiceMessage = document.getElementById('level-choice-message');

    if (selectedLevel) {
        const levelValue = selectedLevel.value;
        // Mettez à jour la valeur du champ caché avec le texte du niveau
        const userLevelElement = document.getElementById('user-level');
        if (userLevelElement) {
            userLevelElement.value = levelValue;
        }
        // Récupérer le niveau sélectionné et démarrer le quiz
        const niveau = selectedLevel.value;

        if (document.getElementById('user-level') && document.getElementById('quiz-questions-container')) {
            try {
                // Récupérer les paramètres du minuteur
                const timerSettings = getTimerSettings();
                // Vérifier si timerSettings est valide avant d'accéder à ses propriétés
                const isTimerSettingsValid = timerSettings !== null && typeof timerSettings === 'object';

                // Si le minuteur est activé et les paramètres sont valides, sauvegarder les paramètres via AJAX
                if (isTimerSettingsValid && timerSettings.enableTimer) {
                    const data = new FormData();
                    data.append('action', 'save_timer_settings');
                    data.append('enable_timer', 'yes');
                    data.append('timer_duration', timerSettings.timerDuration);
                    data.append('nonce', fqi3Data.session.nonce);

                    const response = await fetch(fqi3Data.session.ajax_url, {
                        method: 'POST',
                        body: data
                    });
                    if (!response.ok) {
                        throw new Error('Failed to save timer settings.');
                    }
                    const responseData = await response.json();
                    if (responseData.success) {
                        // Démarrer le minuteur après avoir sauvegardé les paramètres
                        startTimer(timerSettings.timerDuration * 60); // Convertir les minutes en secondes
                        // Assurer que le conteneur de questions n'est pas nul avant de modifier la propriété d'affichage
                        const quizQuestionsContainer = document.getElementById('quiz-questions-container');
                        if (quizQuestionsContainer) {
                            quizQuestionsContainer.style.display = 'block';
                        }
                    }
                } else {
                    // Assurez-vous que le minuteur n'affiche rien si désactivé
                    const timerDisplay = document.getElementById('timer-display');
                    if (timerDisplay) {
                        timerDisplay.style.display = 'none';
                    }
                }

                // Démarrer le quiz avec le niveau sélectionné
                startQuiz(niveau);
                // Appeler la fonction pour incrémenter le compte de quiz
                incrementQuizCount();
            } catch (error) {
                console.error('An unexpected error occurred during the fetch operation.');
            } finally {
                // Assurez-vous que l'indicateur de chargement est masqué
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
            }
        }
    } else if (choiceMessage) {
        // Afficher un message demandant de sélectionner un niveau
        choiceMessage.innerText = fqi3Data.translations.select_level;
    }
}

/*
* The startQuiz function initiates a quiz by fetching questions from a server 
* based on the specified level (niveau). 
* It manages the display of loading indicators, error messages, and quiz sections, 
* and updates the UI with the fetched questions or error information.
*/
function startQuiz(niveau) {
    if (typeof niveau !== 'string' || niveau.trim() === '') {
        console.error('Invalid niveau parameter');
        return;
    }

    try {
        // Afficher l'indicateur de chargement
        const loadingIndicator = document.getElementById('loading-indicator');
        if (loadingIndicator) {
            loadingIndicator.style.display = 'block';
        }

        // Utiliser fetch pour récupérer les questions depuis le serveur
        fetch(`${fqi3Data.cookies.ajax_url}?action=get_questions&niveau=${niveau}`)
        .then(response => response.json())
        .then(data => {
            // Masquer l'indicateur de chargement
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }

            const errorMessage = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');
            const quizQuestionsContainer = document.getElementById('quiz-questions-container');
            const levelContainer = document.getElementById('level-container');
            const startContainer = document.getElementById('start-container');

            if (data.error) {
                // Afficher le message d'erreur   
                if (errorText) {
                    errorText.innerHTML = data.error;
                }
                
                if (errorMessage) {
                    errorMessage.style.display = 'block';
                }

                // Masquer les sections du quiz et de sélection des niveaux
                if (levelContainer) {
                    levelContainer.style.display = 'none';
                }
                if (startContainer) {
                    startContainer.style.display = 'none';
                }
                if (quizQuestionsContainer) {
                    quizQuestionsContainer.style.display = 'none';
                }
            } else {
                // Masquer le message d'erreur s'il était visible
                if (errorMessage) {
                    errorMessage.style.display = 'none';
                }

                // Afficher les sections du quiz
                if (levelContainer) {
                    levelContainer.style.display = 'none';
                }
                if (startContainer) {
                    startContainer.style.display = 'none';
                }
                if (quizQuestionsContainer) {
                    quizQuestionsContainer.style.display = 'block';
                }

                // Charger les questions
                questions = data;
                totalQuestions = questions.length;

                loadQuestion(niveau);
            }
            // Mettre à jour le span avec le texte du niveau après l'injection du message d'erreur
            const userLevelInput = document.getElementById('user-level');
            const levelChooseSpan = document.getElementById('levelChoose');
            if (userLevelInput && levelChooseSpan) {
                const userLevelValue = userLevelInput.value;
                //levelChooseSpan.textContent = userLevelValue;
                const humanReadableLevel = fqi3Data.config.levels[userLevelValue] || userLevelValue; // Utilise la value par défaut si elle n'est pas trouvée
                levelChooseSpan.textContent = humanReadableLevel;
            }
        })
        .catch(error => {
            // Masquer l'indicateur de chargement en cas d'erreur
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }

            // Afficher un message d'erreur général
            const errorMessage = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');
            
            if (errorText) {
                errorText.innerHTML = fqi3Data.translations.loading_error;
            }
            
            if (errorMessage) {
                errorMessage.style.display = 'block';
            }

            // Masquer les sections du quiz et de sélection des niveaux
            const quizQuestionsContainer = document.getElementById('quiz-questions-container');
            const levelContainer = document.getElementById('level-container');
            const startContainer = document.getElementById('start-container');

            if (levelContainer) {
                levelContainer.style.display = 'none';
            }
            if (startContainer) {
                startContainer.style.display = 'none';
            }
            if (quizQuestionsContainer) {
                quizQuestionsContainer.style.display = 'none';
            }
        })
        .finally(() => {
            // Assurez-vous que l'indicateur de chargement est masqué
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        });
    } catch (error) {
        // Handle any errors that occur during the asynchronous operation
        console.error(`An error occurred: ${error}`);
    }
}

/*
* This code defines an asynchronous function incrementQuizCount that sends 
* a POST request to a server endpoint to increment a quiz count. 
* It handles potential errors in the request and logs them to the console.
*/
async function incrementQuizCount() {
    try {
        const response = await fetch(`${fqi3Data.cookies.ajax_url}?action=increment_quiz_count&nonce=${fqi3Data.cookies.nonce}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        if (!response.ok) {
            throw new Error('Failed to increment quiz count.');
        }
        const data = await response.json();
        if (!data.success) {
            console.error('Failed to increment quiz count.');
        }
    } catch (error) {
        console.error('An error has occurred while incrementing the quiz count.', error);
    }
}

/**
 * Shuffles the elements of an array.
 * @param {Array} array - The input array to be shuffled.
 * @returns {Array} - The shuffled array.
 */
function shuffleArray(array) {
    const shuffledArray = [...array];
    for (let i = shuffledArray.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffledArray[i], shuffledArray[j]] = [shuffledArray[j], shuffledArray[i]];
    }
    return shuffledArray;
}

/**
 * Loads and displays the current quiz question and its options based on the specified level.
 *
 * This function checks for essential prerequisites like the availability of questions and
 * a valid current question index. It also verifies that the options are in the correct format
 * before proceeding. Each option is assigned a unique key to ensure reliable display and functionality.
 * The options are then shuffled for randomization before being rendered in the options container.
 *
 * @param {string} niveau - The selected quiz level, used as context to load appropriate questions.
 */
function loadQuestion(niveau) {
    if (!questions || typeof currentQuestionIndex !== 'number') {
        console.error('Questions or currentQuestionIndex is not defined. Cannot proceed with loading questions.');
        return;
    }

    const currentQuestion = questions[currentQuestionIndex];

    // Ajoutez ces déclarations pour vous assurer que currentQuestion est défini
    if (!currentQuestion) {
        return;
    };

    // Vérifier le format des options avant de les utiliser
    if (!Array.isArray(currentQuestion.options)) {
        return;
    };

    // Ajoute une clé unique à chaque option avant de mélanger
    const originalOptions = currentQuestion.options.map((option, index) => ({
        value: option,
        key: index
    }));

    // Mélange les options
    const shuffledOptions = shuffleArray(originalOptions); // Fonction pour mélanger

    document.getElementById('question-title').innerText = `${fqi3Data.translations.question} ${currentQuestionIndex + 1} ${fqi3Data.translations.on} ${questions.length}`;

    // Afficher la question et les options (à adapter selon votre structure de données)
    const optionsContainer = document.getElementById('options-container');
    if (!optionsContainer) {
        console.error('Options container is null. Cannot proceed with DOM manipulation.');
        return;
    }
    optionsContainer.innerHTML = "";

    displayQuestionElements(currentQuestion, optionsContainer, shuffledOptions);

    // Mettre à jour les indicateurs de progression
    updateProgressIndicators();
}

/**
 * Renders the quiz question and its shuffled options in the specified container element.
 *
 * This function generates HTML elements for the question and its options, appending them to
 * the designated `optionsContainer`. If the current question contains a secondary question
 * (q2), it is also displayed. Options are displayed as buttons, each mapped to a unique key
 * for identification. The function dynamically creates elements to ensure that questions and
 * options are displayed correctly and interactively.
 *
 * @param {Object} currentQuestion - The current question object containing the main question (q),
 *                                   optional secondary question (q2), and options.
 * @param {HTMLElement} optionsContainer - The HTML container element where the question and options
 *                                         will be rendered.
 * @param {Array} shuffledOptions - The list of option objects, each containing a `value` (text) and
 *                                  `key` (unique identifier), shuffled for random order display.
 */
function displayQuestionElements(currentQuestion, optionsContainer, shuffledOptions) {
    const questionElement = createQuestionElement(currentQuestion.q, 'question-text');
    optionsContainer.appendChild(questionElement);

    if (currentQuestion.q2) {
        const question2Element = createQuestionElement(currentQuestion.q2, 'question-text2');
        optionsContainer.appendChild(question2Element);
    }

    const optionContainer = document.createElement('div');
    optionContainer.classList.add('option-container');

    shuffledOptions.forEach((option, index) => {
        const button = createOptionButton(option.value, option.key, index);
        optionContainer.appendChild(button);
    });

    optionsContainer.appendChild(optionContainer);
}

/**
 * Creates a paragraph element for a quiz question with specified text and CSS class.
 *
 * This function generates a paragraph (`<p>`) element, assigns it the provided text content,
 * and applies the given CSS class for styling. It returns the created element, ready for
 * appending to the DOM.
 *
 * @param {string} text - The text content of the question to be displayed.
 * @param {string} cssClass - The CSS class to apply for styling the question element.
 * @returns {HTMLElement} The created paragraph element containing the question text.
 */
function createQuestionElement(text, cssClass) {
    const questionElement = document.createElement('p');
    questionElement.textContent = text;
    questionElement.classList.add(cssClass);
    return questionElement;
}

/**
 * Creates a button element for a quiz option with specified value, key, and index.
 *
 * This function generates a button element, sets its text content to the provided
 * option value, and applies an 'option' CSS class for styling. It also sets custom
 * data attributes to store the option's key and index, and attaches an `onclick` event 
 * handler to manage option selection and reset styling of other options.
 *
 * @param {string} value - The display text of the option button.
 * @param {number} key - The unique key associated with this option for identification.
 * @param {number} index - The position index of the option within the shuffled array.
 * @returns {HTMLElement} The created button element for the quiz option.
 */
function createOptionButton(value, key, index) {
    const button = document.createElement('button');
    button.textContent = value;
    button.classList.add('option');
    button.setAttribute('data-key', key);
    button.setAttribute('data-index', index);
    button.onclick = () => {
        selectOption(button, key);
        resetOptionsClasses();
    };
    return button;
}

/**
 * Updates the visual indicators of the user's progress through the quiz.
 */
function updateProgressIndicators() {
    const answersIndicatorContainer = document.getElementById('answers-indicator-container');
    
    if (!answersIndicatorContainer || !Array.isArray(questions) || typeof currentQuestionIndex !== 'number') {
        return;
    }
    
    // Nettoyer le conteneur des indicateurs de progression
    answersIndicatorContainer.innerHTML = "";
    
    // Utiliser un document fragment pour améliorer les performances
    const fragment = document.createDocumentFragment();
    
    // Ajouter les indicateurs de réponses en fonction de la progression
    for (let i = 0; i < questions.length; i++) {
        const answerIndicator = document.createElement('div');
        answerIndicator.classList.add('answer-indicator');
        
        // Ajouter la classe current-progress pour les indicateurs de progression actuelle
        if (i <= currentQuestionIndex) {
            answerIndicator.classList.add('current-progress');
        }
        
        fragment.appendChild(answerIndicator);
    }
    
    answersIndicatorContainer.appendChild(fragment);
}

/**
 * Checks whether the user's selected answer is correct for the current question.
 *
 * @param {number} selectedIndex - The index of the selected answer option.
 */
function checkAnswer(selectedIndex) {
    const currentQuestion = questions[currentQuestionIndex];
    const correctIndex = parseInt(currentQuestion.answer); // L'index correct dans la liste des options originales

    const selectedOptionElement = document.querySelector('.option.selected');
    if (currentQuestion && selectedOptionElement) {
        const selectedKey = selectedOptionElement.getAttribute('data-key');
        const correctKey = correctIndex.toString();

        markAnswerCorrectness(selectedOptionElement, selectedKey, correctKey);

        // Si la réponse est incorrecte, enregistrez-la
        if (selectedKey !== correctKey) {
            const questionText = currentQuestion.q;
            storeIncorrectAnswer(currentQuestionIndex, questionText, selectedKey, correctKey);
        }
    }
}

/**
 * Marks the selected option as correct or wrong based on the comparison of keys.
 *
 * @param {HTMLElement} selectedOptionElement - The DOM element of the selected option.
 * @param {string} selectedKey - The key of the selected option.
 * @param {string} correctKey - The key of the correct answer.
 */
function markAnswerCorrectness(selectedOptionElement, selectedKey, correctKey) {
    if (selectedKey === correctKey) {
        selectedOptionElement.classList.add('correct');
        correctAnswers++;
    } else {
        selectedOptionElement.classList.add('wrong');
    }
}

// Stocker une réponse incorrecte dans sessionStorage
function storeIncorrectAnswer(questionIndex, question, selectedKey, correctKey) {
    const incorrectAnswers = JSON.parse(sessionStorage.getItem('incorrectAnswers')) || [];

    const incorrectAnswer = {
        question: question,
        selectedAnswer: questions[questionIndex].options[selectedKey],
        correctAnswer: questions[questionIndex].options[correctKey]
    };

    incorrectAnswers.push(incorrectAnswer);
    sessionStorage.setItem('incorrectAnswers', JSON.stringify(incorrectAnswers));
}

/**
* Displays incorrect answers in an HTML table.
*
* This function retrieves incorrect answers stored in the session,
* and dynamically builds the table body with this data.
*
* - If incorrect answers exist, it creates one row per answer
* with the question, the selected answer, and the correct answer.
* - If no incorrect answers are present, it displays a congratulations message.
* 
* @function
* @name showIncorrectAnswers
* @requires sessionStorage
*/
function showIncorrectAnswers() {
    const storedIncorrectAnswers = JSON.parse(sessionStorage.getItem('incorrectAnswers')) || [];
    const tbody = document.querySelector('#incorrect-answers-table tbody');

    if (storedIncorrectAnswers.length > 0) {
        let rowsHTML = '';
        storedIncorrectAnswers.forEach(answer => {
            rowsHTML += `
                <tr>
                    <td style="padding: 10px; border: 1px solid #ccc;">${answer.question}</td>
                    <td style="padding: 10px; border: 1px solid #ccc;">${answer.selectedAnswer}</td>
                    <td style="padding: 10px; border: 1px solid #ccc;">${answer.correctAnswer}</td>
                </tr>
            `;
        });
        tbody.innerHTML = rowsHTML; // Injecter les lignes dans le tableau
    } else {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" style="padding: 10px; text-align: center; border: 1px solid #ccc;">
                    Bravo, toutes les réponses sont correctes !
                </td>
            </tr>
        `;
    }
}

/**
* Downloads the contents of the incorrect answers table in CSV format.
*
* This function:
* - Selects all rows in the table (headers and data)
* - Transforms each row into a formatted CSV string
* - Creates a temporary download link
* - Automatically triggers the download of the CSV file
*
* Features:
* - Escapes quotes in cells
* - Correctly encodes special characters
* - Cleans up cell text (removes extra spaces)
* 
* @function
* @name downloadTableAsCSV
* @requires document.getElementById
*/
function downloadTableAsCSV() {
    const table = document.getElementById("incorrect-answers-table");
    const csv = [...table.querySelectorAll("tr")]
        .map(row => 
            [...row.querySelectorAll("td, th")]
                .map(cell => `"${cell.textContent.trim().replace(/"/g, '""')}"`)
                .join(",")
        )
        .join("\n");

    const now = new Date();
    const formattedDate = now.toISOString().replace(/[:T]/g, '-').split('.')[0];

    const hiddenElement = document.createElement("a");
    hiddenElement.href = `data:text/csv;charset=utf-8,${encodeURIComponent(csv)}`;
    hiddenElement.download = "incorrect_answers_${formattedDate}.csv";
    
    document.body.appendChild(hiddenElement);
    hiddenElement.click();
    document.body.removeChild(hiddenElement);
}


/**
 * Handles the selection of an answer option by the user.
 *
 * @param {HTMLElement} chosenButton - The button element that the user has selected.
 * @param {number} selectedIndex - The index of the selected option.
 */
function selectOption(chosenButton, selectedIndex) {
    const reappuyerElement = document.getElementById('reappuyer');
    if (reappuyerElement) {
        reappuyerElement.innerText = "";
    }
    // Si une réponse a déjà été sélectionnée, ne rien faire
    if (answerSelected) {
        return;
    }

    // Ajouter la classe 'selected' à l'option choisie
    const selectedOption = document.querySelector('.option.selected');
    if (selectedOption) {
        selectedOption.classList.remove('selected');
    }

    chosenButton.classList.add('selected');

    try {
        // Vérifier la réponse immédiatement après avoir sélectionné une option
        checkAnswer(selectedIndex);
    } catch (error) {
        console.error(`Error in checkAnswer: ${error}`);
        // Handle the error accordingly
    }

    // Désactiver la sélection d'options après la première sélection
    answerSelected = true;
}

/**
 * Displays the results of the quiz once the user has completed it.
 */
function showResults() {
    const quizQuestionsContainer = document.getElementById('quiz-questions-container');
    const resultContainer = document.getElementById('result-container');
    const resultText = document.getElementById('result-text');
    const userLevelValue = document.getElementById('user-level').value;
    const userLevelLabel = fqi3Data.config.levels[userLevelValue] || userLevelValue; // Obtenir le label lisible

    quizQuestionsContainer.style.display = 'none';
    resultContainer.style.display = 'block';

    const percentage = calculatePercentage();

    // Gestion du singulier/pluriel pour le nombre de réponses correctes
    const correctAnswersText = getCorrectAnswersText();

    resultText.innerText = correctAnswersText
        .replace('%1$s', correctAnswers)
        .replace('%2$s', questions.length)
        .replace('%3$s', percentage.toFixed(2))
        .replace('%4$s', userLevelLabel);
        if (fqi3Data.isUserLoggedIn && !fqi3Data.config.disable_statistics) {
            sendQuizStatistics(userLevelValue, correctAnswers, questions.length)
                .then(() => {
                    awardBadges(userLevelValue);
                })
                .catch((error) => {
                    console.error('Error in sending quiz statistics:', error);
                });
        }

    if (percentage >= PERCENTAGE_THRESHOLD) {
        launchConfetti();
    }

    setTimeout(() => {
        showIncorrectAnswers();
    }, 500); 
}

/**
 * Calculates the percentage of correct answers.
 *
 * @returns {number} The percentage of correct answers out of the total questions.
 */
function calculatePercentage() {
    return (correctAnswers / questions.length) * 100;
}

/**
 * Retrieves the appropriate text for the number of correct answers,
 * handling singular and plural forms based on the count.
 *
 * @returns {string} The result text for correct answers, either singular or plural.
 */
function getCorrectAnswersText() {
    return correctAnswers === 1
        ? fqi3Data.translations.result_text_singular
        : fqi3Data.translations.result_text_plural;
}

/**
 * Launches a confetti animation to celebrate an achievement, such as 
 * completing a quiz or reaching a milestone. The confetti is generated 
 * with specific visual properties to create an engaging effect.
 */
function launchConfetti() {
    confetti({
        particleCount: 100, // Nombre de confettis
        angle: 90, // Direction
        spread: 70, // Écart
        startVelocity: 30, // Vitesse de départ
        decay: 0.9, // Décroissance
        scalar: 1.2, // Échelle
        colors: ['#ff0', '#0f0', '#00f', '#f00', '#f0f', '#0ff'] // Couleurs des confettis
    });
}

/**
 * Restarts the quiz by resetting relevant variables and displaying the first 
 * question. This function handles the case where a level is selected, allowing 
 * the quiz to reload from the beginning of that level.
 */
function restartQuiz() {
    const selectedLevelElement = document.querySelector('input[name="niveau"]:checked');
    if (selectedLevelElement) {
        selectedLevel = selectedLevelElement.value; // Mettre à jour la variable selectedLevel
        currentQuestionIndex = 0;
        correctAnswers = 0;
        document.getElementById('result-container').style.display = 'none';
        document.getElementById('quiz-questions-container').style.display = 'block';
        loadQuestion(selectedLevel); // Charger la première question du niveau sélectionné
    } else {
        alert("No level selected.");
    }
}

/**
 * Advances to the next question in the quiz if an answer has been selected.
 * If no answer is selected, prompts the user to select an answer before proceeding.
 */
function nextQuestion() {
    if (!answerSelected) {
        const reappuyerElement = document.getElementById('reappuyer');
        if (reappuyerElement) {
            reappuyerElement.innerText = fqi3Data.translations.select_answer;
        } else {
            console.error('Element with id "reappuyer" not found.');
        }
        return;
    }
    answerSelected = false; // Réinitialiser pour permettre la sélection pour la prochaine question
    currentQuestionIndex++;

    if (currentQuestionIndex < questions.length) {
        loadQuestion();
    } else {
        showResults();
    }
}

/**
 * Resets the classes for each option button in the quiz to visually indicate 
 * the correctness of each option based on the current question's correct answer.
 */
function resetOptionsClasses() {
    const options = document.querySelectorAll('.option');
    const currentQuestion = questions[currentQuestionIndex];
    const correctIndex = parseInt(currentQuestion.answer);

    options.forEach((option) => {
        const isCorrect = option.dataset.key === correctIndex.toString();
        option.classList.toggle('correct', isCorrect);
        option.classList.toggle('wrong', !isCorrect);
    });
}

/**
 * Redirects the user back to the origin URL if available, or to the homepage if not.
 */
function goBackToOrigin() {
    const originUrl = localStorage.getItem('originUrl');
    if (originUrl) {
        window.location.href = originUrl; // Redirige vers l'URL d'origine
    } else {
        window.location.href = "/"; // Si l'URL n'est pas trouvée, redirige vers l'accueil par défaut
    }
}

/**
 * Handles the quiz restart action by calling the `restartQuiz` function.
 */
function handleRestartQuiz() {
    restartQuiz(); // Appeler la fonction existante
}

/**
 * Handles the action to go back to the origin URL by calling `goBackToOrigin`.
 */
function handleGoBackToOrigin() {
    goBackToOrigin(); // Appeler la fonction existante
}

// Gestion timer
// Fonction pour récupérer les paramètres de minuterie
function getTimerSettings() {
    const enableTimerElement = document.getElementById('enable-timer');
    const timerDurationElement = document.getElementById('timer-duration');

    if (!enableTimerElement || !timerDurationElement) {
        return null;
    }
    return {
        enableTimer: document.getElementById('enable-timer').checked,
        timerDuration: parseInt(document.getElementById('timer-duration').value, 10)
    };
}

/**
 * Function to start a countdown timer and display the remaining time.
 * 
 * @param {number} duration - Duration in seconds for the countdown.
 * 
 */
function startTimer(duration) {
    // Logique pour démarrer le minuteur
    const timerDisplay = document.getElementById('timer-display');
    const timerContainer = document.createElement('div');
    timerContainer.id = 'timer-container';
    timerDisplay.appendChild(timerContainer);
    let timer = duration, minutes, seconds;
    const intervalId = setInterval(function () {
        minutes = Math.floor(timer / 60);
        seconds = timer % 60;
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        if (timerContainer) {
            timerContainer.textContent = minutes + ":" + seconds;
        }

        if (--timer < 0) {
            clearInterval(intervalId); // Arrêter le minuteur
            if (timerContainer) {
                timerContainer.textContent = "00:00";
                timerContainer.classList.add("time-end");
            }
            showEndMessage(); // Afficher le message de fin
        }
    }, 1000);

    function showEndMessage() {
        const existingMessage = document.getElementById('timer-end-message');
        const timerDisplay = document.getElementById('timer-display');
        if (existingMessage) {
            return; // Le message est déjà affiché, ne rien faire
        }

        const messageContainer = document.createElement('div');
        messageContainer.id = 'timer-end-message';
        messageContainer.textContent = fqi3Data.translations.messageTimer;
        timerDisplay.appendChild(messageContainer);
    
        // Vous pouvez ajouter des styles ou des classes pour personnaliser l'apparence du message
        function startTimer(duration) {
            // Logique pour démarrer le minuteur
            const timerDisplay = document.getElementById('timer-display');
            if (!timerDisplay) {
                return; // Handle the case where timerDisplay is null
            }
            const timerContainer = document.createElement('div');
            timerContainer.id = 'timer-container';
            timerDisplay.appendChild(timerContainer);
            let timer = duration, minutes, seconds;
            const intervalId = setInterval(function () {
                minutes = Math.floor(timer / 60);
                seconds = timer % 60;
                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                if (timerContainer) {
                    timerContainer.textContent = minutes + ":" + seconds;
                }

                if (--timer < 0) {
                    clearInterval(intervalId); // Arrêter le minuteur
                    if (timerContainer) {
                        timerContainer.textContent = "00:00";
                        timerContainer.classList.add("time-end");
                    }
                    showEndMessage(); // Afficher le message de fin
                }
            }, 1000);

            function showEndMessage() {
                const existingMessage = document.getElementById('timer-end-message');
                const timerDisplay = document.getElementById('timer-display');
                if (existingMessage) {
                    return; // Le message est déjà affiché, ne rien faire
                }

                const messageContainer = document.createElement('div');
                messageContainer.id = 'timer-end-message';
                messageContainer.textContent = fqi3Data.translations.messageTimer;
                timerDisplay.appendChild(messageContainer);
    
                // Vous pouvez ajouter des styles ou des classes pour personnaliser l'apparence du message
                messageContainer.classList.add('end-message');
            }
        }
    }
}

// Social Network share management
/**
 * Generates the text for sharing quiz results.
 * 
 * @param {number} correctAnswers - Number of correct answers achieved by the user.
 * @param {number} totalQuestions - Total number of questions in the quiz.
 * @returns {string} - The formatted text with placeholders replaced by actual values.
 */
function getPostText(correctAnswers, totalQuestions) {
    const resultTextTemplate = fqi3Data.translations.share_post_text;
    return resultTextTemplate
        .replace('%1$s', siteUrl)
        .replace('%2$s', correctAnswers)
        .replace('%3$s', totalQuestions)
        .replace('%4$s', ((correctAnswers / totalQuestions) * 100).toFixed(2));
}
function shareOnFacebook() {
    const postText = getPostText(correctAnswers, totalQuestions);
    const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(siteUrl)}&quote=${encodeURIComponent(postText)}`;
    window.open(shareUrl, '_blank');
}

function shareOnX() {
    const postText = getPostText(correctAnswers, totalQuestions);
    const shareUrl = `https://x.com/intent/tweet?url=${encodeURIComponent(siteUrl)}&text=${encodeURIComponent(postText)}`;
    window.open(shareUrl, '_blank');
}

function shareOnLinkedIn() {
    const postText = getPostText(correctAnswers, totalQuestions);
    const shareUrl = `https://www.linkedin.com/shareArticle?mini=true&url=${encodeURIComponent(siteUrl)}&text=${encodeURIComponent(postText)}`;
    window.open(shareUrl, '_blank');
}

// Statistics management
/**
 * Sends quiz statistics to the server for storage or processing.
 * 
 * @param {string} level - The quiz level selected by the user.
 * @param {number} correctAnswers - Number of correct answers given by the user.
 * @param {number} totalQuestions - Total number of questions in the quiz.
 * @returns {Promise} - Resolves with the server response if successful; rejects with an error message or error object if unsuccessful.
 */
function sendQuizStatistics(level, correctAnswers, totalQuestions) {
    return new Promise((resolve, reject) => {
        const data = {
            action: 'update_quiz_statistics',
            security: fqi3Data.cookies.nonce,
            level: level,
            correct_answers: correctAnswers,
            total_questions: totalQuestions
        };

        jQuery.post(fqi3Data.cookies.ajax_url, data)
            .done((response) => {
                if (response.success) {
                    resolve(response);
                } else {
                    reject(response.data); // En cas d'échec, rejetez avec le message d'erreur
                }
            })
            .fail((error) => {
                reject(error); // En cas d'erreur AJAX, rejetez avec l'erreur
            });
    });
}

// Badge management
/**
 * Sends a request to the server to award badges based on the quiz level.
 * 
 * @param {string} level
 */
function awardBadges(level) {
    const data = {
        action: 'award_badges',
        security: fqi3Data.cookies.nonce,
        level: level,
        user_id: fqi3Data.user_id
    };

    jQuery.post(fqi3Data.cookies.ajax_url, data, function(response) {
        if (!response.success) {
            console.error('Failed to award badges:', response.data);
        }
    });
}