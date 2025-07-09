<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bible Drag & Drop Quiz</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .droppable {
            transition: all 0.3s ease;
            min-height: 80px;
        }
        .draggable {
            transition: all 0.2s ease;
            user-select: none;
        }
        .dragging {
            opacity: 0.5;
            transform: scale(1.05);
        }
        .correct-drop {
            background-color: #d1fae5 !important;
            border-color: #10b981 !important;
        }
        .incorrect-drop {
            background-color: #fee2e2 !important;
            border-color: #ef4444 !important;
        }
        .correct-answer {
            position: relative;
        }
        .correct-answer::after {
            content: "✓";
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #10b981;
            font-weight: bold;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
        }
        .title-font {
            font-family: 'Playfair Display', serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen">
    <div class="container mx-auto px-4 py-12 max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="title-font text-4xl md:text-5xl font-bold text-blue-900 mb-4">Bible Trivia Challenge</h1>
            <p class="text-lg text-blue-800">Drag and drop the correct answers to the questions below</p>
            <div class="w-24 h-1 bg-blue-500 mx-auto mt-4 rounded-full"></div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-8 bg-white rounded-full overflow-hidden shadow-inner">
            <div id="progress-bar" class="h-3 bg-gradient-to-r from-blue-500 to-indigo-600 transition-all duration-500" style="width: 0%"></div>
        </div>

        <!-- Quiz Container -->
        <div id="quiz-container" class="space-y-8">
            <!-- Question 1 -->
            <div class="question-card bg-white rounded-xl shadow-lg overflow-hidden" data-question-id="1">
                <div class="p-6 bg-gradient-to-r from-blue-600 to-indigo-700">
                    <h2 class="text-xl font-bold text-white">Question 1</h2>
                    <p class="text-blue-100 mt-1">Who built the ark?</p>
                </div>
                
                <div class="p-6">
                    <!-- Draggable Options -->
                    <div class="flex flex-wrap gap-3 mb-6">
                        <div draggable="true" class="draggable px-5 py-3 bg-blue-100 text-blue-800 rounded-lg cursor-grab hover:bg-blue-200 shadow-sm active:cursor-grabbing" data-value="Moses">
                            Moses
                        </div>
                        <div draggable="true" class="draggable px-5 py-3 bg-blue-100 text-blue-800 rounded-lg cursor-grab hover:bg-blue-200 shadow-sm active:cursor-grabbing" data-value="Noah">
                            Noah
                        </div>
                        <div draggable="true" class="draggable px-5 py-3 bg-blue-100 text-blue-800 rounded-lg cursor-grab hover:bg-blue-200 shadow-sm active:cursor-grabbing" data-value="Abraham">
                            Abraham
                        </div>
                        <div draggable="true" class="draggable px-5 py-3 bg-blue-100 text-blue-800 rounded-lg cursor-grab hover:bg-blue-200 shadow-sm active:cursor-grabbing" data-value="David">
                            David
                        </div>
                    </div>
                    
                    <!-- Drop Zone -->
                    <div class="droppable p-5 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50" data-correct="Noah">
                        <p class="text-gray-500 text-center">Drag your answer here</p>
                    </div>
                </div>
            </div>

            <!-- Question 2 -->
            <div class="question-card bg-white rounded-xl shadow-lg overflow-hidden" data-question-id="2">
                <div class="p-6 bg-gradient-to-r from-blue-600 to-indigo-700">
                    <h2 class="text-xl font-bold text-white">Question 2</h2>
                    <p class="text-blue-100 mt-1">How many days did God take to create the world?</p>
                </div>
                
                <div class="p-6">
                    <!-- Draggable Options -->
                    <div class="flex flex-wrap gap-3 mb-6">
                        <div draggable="true" class="draggable px-5 py-3 bg-blue-100 text-blue-800 rounded-lg cursor-grab hover:bg-blue-200 shadow-sm active:cursor-grabbing" data-value="3">
                            3
                        </div>
                        <div draggable="true" class="draggable px-5 py-3 bg-blue-100 text-blue-800 rounded-lg cursor-grab hover:bg-blue-200 shadow-sm active:cursor-grabbing" data-value="6">
                            6
                        </div>
                        <div draggable="true" class="draggable px-5 py-3 bg-blue-100 text-blue-800 rounded-lg cursor-grab hover:bg-blue-200 shadow-sm active:cursor-grabbing" data-value="7">
                            7
                        </div>
                        <div draggable="true" class="draggable px-5 py-3 bg-blue-100 text-blue-800 rounded-lg cursor-grab hover:bg-blue-200 shadow-sm active:cursor-grabbing" data-value="10">
                            10
                        </div>
                    </div>
                    
                    <!-- Drop Zone -->
                    <div class="droppable p-5 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50" data-correct="6">
                        <p class="text-gray-500 text-center">Drag your answer here</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="mt-12 flex justify-between items-center">
            <button id="reset-btn" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition shadow">
                Reset All
            </button>
            <button id="check-btn" class="px-8 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-bold rounded-lg hover:from-green-600 hover:to-green-700 transition shadow-lg hover:shadow-xl">
                Check Answers
            </button>
        </div>

        <!-- Results -->
        <div id="results" class="mt-8 hidden">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-blue-900 mb-4">Your Results</h3>
                <div id="score-display" class="text-3xl font-bold text-center mb-4"></div>
                <div id="feedback" class="text-center text-gray-600"></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Drag and Drop Functionality
            const draggables = document.querySelectorAll('.draggable');
            const droppables = document.querySelectorAll('.droppable');
            const checkBtn = document.getElementById('check-btn');
            const resetBtn = document.getElementById('reset-btn');
            const resultsDiv = document.getElementById('results');
            const scoreDisplay = document.getElementById('score-display');
            const feedbackDiv = document.getElementById('feedback');
            const progressBar = document.getElementById('progress-bar');
            const totalQuestions = document.querySelectorAll('.question-card').length;
            
            let draggedItem = null;
            let answers = {};

            // Drag Start
            draggables.forEach(draggable => {
                draggable.addEventListener('dragstart', function() {
                    draggedItem = this;
                    setTimeout(() => {
                        this.classList.add('dragging');
                    }, 0);
                });

                draggable.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                });
            });

            // Drop Events
            droppables.forEach(droppable => {
                droppable.addEventListener('dragover', e => {
                    e.preventDefault();
                    droppable.classList.add('bg-blue-50', 'border-blue-400');
                });

                droppable.addEventListener('dragleave', () => {
                    droppable.classList.remove('bg-blue-50', 'border-blue-400');
                });

                droppable.addEventListener('drop', e => {
                    e.preventDefault();
                    droppable.classList.remove('bg-blue-50', 'border-blue-400');
                    
                    if (draggedItem) {
                        // Remove any existing answer
                        const existingAnswer = droppable.querySelector('.draggable');
                        if (existingAnswer) {
                            droppable.parentNode.querySelector('.flex').appendChild(existingAnswer);
                        }
                        
                        // Add the new answer
                        const clone = draggedItem.cloneNode(true);
                        clone.classList.remove('hover:bg-blue-200');
                        clone.classList.add('cursor-auto', 'w-full');
                        clone.setAttribute('draggable', 'false');
                        droppable.innerHTML = '';
                        droppable.appendChild(clone);
                        
                        // Store the answer
                        const questionId = droppable.closest('.question-card').dataset.questionId;
                        answers[questionId] = draggedItem.dataset.value;
                        
                        // Update progress
                        updateProgress();
                    }
                });
            });

            // Check Answers
            checkBtn.addEventListener('click', () => {
                let correctCount = 0;
                
                droppables.forEach(droppable => {
                    const questionId = droppable.closest('.question-card').dataset.questionId;
                    const userAnswer = answers[questionId];
                    const correctAnswer = droppable.dataset.correct;
                    
                    if (userAnswer === correctAnswer) {
                        droppable.classList.add('correct-drop');
                        droppable.classList.remove('incorrect-drop');
                        correctCount++;
                    } else {
                        droppable.classList.add('incorrect-drop');
                        droppable.classList.remove('correct-drop');
                        
                        // Show correct answer
                        const correctElement = document.createElement('div');
                        correctElement.className = 'text-green-600 font-medium mt-2 text-sm';
                        correctElement.textContent = `Correct answer: ${correctAnswer}`;
                        droppable.appendChild(correctElement);
                    }
                });
                
                // Show results
                const score = Math.round((correctCount / totalQuestions) * 100);
                scoreDisplay.textContent = `${score}% Score`;
                scoreDisplay.className = `text-3xl font-bold text-center mb-4 ${
                    score >= 80 ? 'text-green-600' : 
                    score >= 50 ? 'text-yellow-500' : 'text-red-600'
                }`;
                
                feedbackDiv.textContent = `You answered ${correctCount} out of ${totalQuestions} questions correctly!`;
                resultsDiv.classList.remove('hidden');
                
                // Scroll to results
                resultsDiv.scrollIntoView({ behavior: 'smooth' });
            });

            // Reset All
            resetBtn.addEventListener('click', () => {
                droppables.forEach(droppable => {
                    droppable.innerHTML = '<p class="text-gray-500 text-center">Drag your answer here</p>';
                    droppable.classList.remove('correct-drop', 'incorrect-drop');
                });
                
                answers = {};
                resultsDiv.classList.add('hidden');
                updateProgress();
            });

            // Update progress bar
            function updateProgress() {
                const answered = Object.keys(answers).length;
                const progress = (answered / totalQuestions) * 100;
                progressBar.style.width = `${progress}%`;
            }
        });
    </script>
</body>
</html>