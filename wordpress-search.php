<?php // Tell SearchWP to use <span> for highlights instead of <mark>.
add_filter('searchwp_th_use_span', '__return_true');

function swp_extract_highlighted_terms()
{
    if (is_search()) {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var excludeWords = ['and', 'or', 'the', 'they', 'of', 'for'];
                var detectedLogic = '';
                var isExactMatchSearch = false;
                var search_string = "<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : ''; ?>";
                var search_terms = search_string.match(/"[^"]+"|\S+/g).map(function (str) {
                    return str.replace(/(^")|("$)/g, '').trim();
                });

                if (search_string.startsWith('"') && search_string.endsWith('"')) {
                    isExactMatchSearch = true;
                }

                window.search_terms = search_terms;

                search_terms = search_terms.filter(function (term) {
                    return !excludeWords.includes(term.toLowerCase()) && term.length > 2;
                });

                if (/\bAND\b/.test(search_string)) {
                    detectedLogic = 'AND';
                } else if (/\bOR\b/.test(search_string)) {
                    detectedLogic = 'OR';
                }

                console.log('search_terms:', search_terms);

                let articles = document.querySelectorAll('.elementor-post');

                articles.forEach(article => {
                    let highlightedSpans = article.querySelectorAll('span.searchwp-highlight');
                    highlightedSpans.forEach(span => {
                        let words = span.innerHTML.split(' ');
                        let newContent = '';
                        words.forEach(word => {
                            if (excludeWords.includes(word.trim().toLowerCase()) || word.length <= 2) {
                                newContent += ' ' + word.trim();
                            } else {
                                newContent += ' <span class="searchwp-highlight">' + word.trim() + '</span>';
                            }
                        });
                        span.outerHTML = newContent.trim();
                    });
                });

                let highlightedSpans = document.querySelectorAll('span.searchwp-highlight');
                let highlightedTerms = Array.from(highlightedSpans).map(span => span.textContent);
                highlightedTerms = [...new Set(highlightedTerms)];

                let links = document.querySelectorAll('.elementor-post .elementor-post__title a, .elementor-post .elementor-post__read-more');
                links.forEach(link => {
                    let currentURL = new URL(link.href);
                    currentURL.searchParams.set('highlightedTerms', search_terms.join(' '));
                    if (detectedLogic) {
                        currentURL.searchParams.set('logic_used', detectedLogic);
                    }
                    if (isExactMatchSearch) {
                        currentURL.searchParams.set('is_exact_match', 'true');
                    }
                    link.href = currentURL.toString();
                });
            });
        </script>
        <?php
    }
}


add_action('wp_footer', 'swp_extract_highlighted_terms');

// Adds custom CSS
function acss()
{
    ?>
    <style>
        span.searchwp-highlight {
            background-color: #fcf774;
        }
    </style>
    <?php
}

add_action('wp_head', 'acss');

// Adds mark.js
function add_mark_js()
{
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/mark.js/8.11.1/mark.min.js"></script>';
}

add_action('wp_footer', 'add_mark_js');

if (isset($_GET['highlightedTerms']) && !empty($_GET['highlightedTerms'])) {
    add_action('wp_footer', 'cwpai_enqueue_elementor_editor');
    function cwpai_enqueue_elementor_editor()
    {
        ?>
        <script>
            function sanitizeTerm(term) {
                // Escape special characters for use in regular expressions
                return term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }


            window.onload = function () {
                var urlParams = new URLSearchParams(window.location.search);
                var isExactMatchSearch = urlParams.get('is_exact_match') === 'true';
                var exactMatchFound = false;
                var logicUsed = urlParams.get('logic_used');
                var searchQuery = urlParams.get('highlightedTerms');
                var searchWords = searchQuery ? searchQuery.match(/"[^"]+"|\S+/g).map(str => str.replace(/(^")|("$)/g, '').trim()) : [];
                let hasExactMatch = false;


                function scanForExactMatch(searchQuery, text) {
                    const regex = new RegExp(`\\b${searchQuery}\\b`, 'i'); // Case insensitive
                    return regex.test(text);
                }

                // New Logic: Decide what terms to highlight based on isRealExactMatch
                var isRealExactMatch = isExactMatchSearch || hasExactMatch;
                var termsToHighlight;
                if (isRealExactMatch) {
                    termsToHighlight = [searchQuery.replace(/(^")|("$)/g, '').trim()];  // Remove quotes
                } else {
                    termsToHighlight = searchWords;  // Already split and filtered
                }

                console.log('Logic detected:', logicUsed);
                console.log('Is exact match:', isExactMatchSearch);
                console.log('Search words:', searchWords);

                var context = document.querySelector('.elementor-toggle');

                var instance = new Mark(context);

                if (context) {
                    console.log('Context element found');
                } else {
                    console.log('Context element not found');
                }
                var accuracyValue = isRealExactMatch ? 'exactly' : 'partially';

                instance.mark(searchWords, {
                    'element': 'span',
                    'className': 'searchwp-highlight',
                    'accuracy': accuracyValue,
                    'filter': function (textNode, matchTerm, totalCounter, counter) {
                        var cleanText = textNode.textContent.replace(/[^\x20-\x7E]+/g, '');
                        var sanitizedMatchTerm = sanitizeTerm(matchTerm);

                        var regex;

                        // Exact Match logic
                        if (exactMatchFound) { // Use the value of exactMatchFound from outer scope
                            regex = new RegExp(`(?:^|\\s|\\b)${sanitizeTerm(sanitizedMatchTerm)}(?:$|\\s|[,.!?\\-]|\\b)`, 'gi');
                        }
                        // Partial Match logic
                        else {
                            regex = new RegExp(`(?:^|\\s|\\b)${sanitizeTerm(sanitizedMatchTerm)}(?:$|\\s|[,.!?\\-]|\\b)`, 'gi');
                        }

                        var match;
                        var validMatches = [];
                        while ((match = regex.exec(cleanText)) !== null) {
                            validMatches.push(match[0]);
                        }

                        // Extra logic to highlight individual numbered/hyphenated terms within an exact match
                        if (exactMatchFound) {
                            let specialTerms = sanitizedMatchTerm.match(/\b[\w-]+\b/g);
                            if (specialTerms) {
                                specialTerms.forEach(term => {
                                    let specialRegex = new RegExp(`(?:^|\\s|\\b)${sanitizeTerm(term)}(?:$|\\s|[,.!?\\-]|\\b)`, 'gi');
                                    while ((match = specialRegex.exec(cleanText)) !== null) {
                                        validMatches.push(match[0]);
                                    }
                                });
                            }
                        }

                        return validMatches.length > 0;
                    }
                });  // Removed the trailing comma



                var toggleTitles = document.querySelectorAll('.elementor-tab-title');
                var toggleContents = document.querySelectorAll('.elementor-tab-content');

                var toggleTitlesArray = Array.from(toggleTitles);
                var toggleContentsArray = Array.from(toggleContents);

                toggleContents.forEach(content => {
                    let contentText = content.textContent || content.innerText;
                    if (scanForExactMatch(searchQuery, contentText)) {
                        hasExactMatch = true;
                    }
                });

                // Determine if there is a real exact match
                var isRealExactMatch = exactMatchFound || hasExactMatch;
                var toggleHighlights = [];

                // If "AND" logic is used
                if (logicUsed === 'AND') {
                    toggleTitles.forEach(title => {
                        if (searchWords.every(word => title.textContent.toLowerCase().includes(word.toLowerCase()))) {
                            scrollToToggle = title;
                        }
                    });

                    if (!scrollToToggle) {
                        var maxCount = 0;
                        toggleContents.forEach(content => {
                            var highlightCount = searchWords.reduce((count, word) => count + (content.textContent.toLowerCase().match(new RegExp("\\b" + word + "\\b", 'gi')) || []).length, 0);
                            if (highlightCount > maxCount) {
                                maxCount = highlightCount;
                                scrollToToggle = content;
                            }
                        });
                    }

                    // Open all relevant toggles that contain all search terms
                    toggleContents.forEach(content => {
                        if (searchWords.every(word => content.textContent.toLowerCase().includes(word.toLowerCase()))) {
                            content.style.display = 'block';
                        }
                    });
                }
                // OR logic
                else if (logicUsed === 'OR') {
                    toggleTitles.forEach(title => {
                        if (searchWords.some(word => title.textContent.toLowerCase().includes(word.toLowerCase()))) {
                            scrollToToggle = title;
                        }
                    });

                    if (!scrollToToggle) {
                        var maxCount = 0;
                        toggleContents.forEach(content => {
                            var highlightCount = searchWords.reduce((count, word) => count + (content.textContent.toLowerCase().match(new RegExp("\\b" + word + "\\b", 'gi')) || []).length, 0);
                            if (highlightCount > maxCount) {
                                maxCount = highlightCount;
                                scrollToToggle = content;
                            }
                        });
                    }

                    // Open all relevant toggles that contain either of the search terms
                    toggleContents.forEach(content => {
                        if (searchWords.some(word => content.textContent.toLowerCase().includes(word.toLowerCase()))) {
                            content.style.display = 'block';
                        }
                    });
                }


                toggleContents.forEach(content => {
                    var highlightCount = searchWords.reduce((count, word) => count + (content.textContent.toLowerCase().match(new RegExp("\\b" + word + "\\b", 'gi')) || []).length, 0);
                    if (highlightCount > 0) {
                        toggleHighlights.push({
                            toggle: content,
                            count: highlightCount
                        });
                    }
                });



                // Sort based on the highest number of highlights
                toggleHighlights.sort((a, b) => b.count - a.count);

                // Check if at least 3 toggles are highlighted
                var atLeastThreeTogglesHighlighted = toggleHighlights.length >= 3;

                // Check if any of the top 3 toggles have highlighted terms in their titles
                var topToggleTitleHighlighted = false;
                toggleTitles.forEach(title => {
                    if (searchWords.some(word => title.textContent.toLowerCase().includes(word.toLowerCase()))) {
                        topToggleTitleHighlighted = true;
                    }
                });
                toggleTitles.forEach(title => {
                    if (scanForExactMatch(searchQuery, title.textContent)) {
                        exactMatchFound = true;
                    }
                });



                if (atLeastThreeTogglesHighlighted && !topToggleTitleHighlighted) {
                    // New logic here
                    console.log('At least three toggles highlighted:', atLeastThreeTogglesHighlighted);
                    console.log('Top toggle title highlighted:', topToggleTitleHighlighted);
                    var exactMatchToggle = null;
                    var firstHighlightedToggle = null;

                    toggleTitles.forEach(title => {
                        if (title.textContent.toLowerCase() === searchQuery.toLowerCase()) {
                            exactMatchToggle = title;
                            exactMatchFound = true;
                        }
                    });

                    toggleTitles.forEach(title => {
                        if (firstHighlightedToggle === null && searchWords.some(word => title.textContent.toLowerCase().includes(word.toLowerCase()))) {
                            firstHighlightedToggle = title;
                        }
                    });

                    if (exactMatchToggle) {
                        scrollToToggle = exactMatchToggle;
                    } else if (firstHighlightedToggle) {
                        scrollToToggle = firstHighlightedToggle;
                    } else {
                        scrollToToggle = toggleHighlights[0].toggle;
                    }
                }
                else {
                    // Step 1: Look for exact match
                    const searchExactPhraseInToggles = (exactPhrase, toggleContents) => {
                        let exactMatchTitleFound = false;
                        let exactMatchContentFound = false;

                        const sanitizedExactPhrase = exactPhrase.toLowerCase().trim();  // Sanitized search phrase

                        // Check for exact phrase match in toggle titles
                        toggleContents.forEach(content => {
                            const toggleTitle = content.querySelector('.toggle-title');
                            if (toggleTitle && toggleTitle.textContent.toLowerCase().trim().includes(sanitizedExactPhrase)) {
                                scrollToToggle = content;
                                exactMatchTitleFound = true;
                            }
                        });

                        // Check for exact phrase match in toggle contents only if no exact title match is found
                        if (!exactMatchTitleFound) {
                            toggleContents.forEach(content => {
                                if (content.textContent.toLowerCase().trim().includes(sanitizedExactPhrase)) {
                                    scrollToToggle = content;
                                    exactMatchContentFound = true;
                                }
                            });
                        }
                        return scrollToToggle;
                    };

                    // Step 2: If no exact match is found, apply existing default logic
                    if (!exactMatchFound) {
                        var bestToggleTitle = null;
                        var bestToggleContent = null;
                        var bestTitleCount = 0;
                        var bestContentCount = 0;

                        // Check toggle titles
                        toggleTitles.forEach(title => {
                            var highlightCount = searchWords.reduce((count, word) => count + (title.textContent.toLowerCase().includes(word.toLowerCase()) ? 1 : 0), 0);

                            if (highlightCount >= 2 && highlightCount > bestTitleCount) {
                                bestTitleCount = highlightCount;
                                bestToggleTitle = title;
                            }
                        });

                        // Check toggle contents
                        toggleContents.forEach(content => {
                            var highlightCount = searchWords.reduce((count, word) => count + (content.textContent.toLowerCase().match(new RegExp("\\b" + word + "\\b", 'gi')) || []).length, 0);
                            if (highlightCount > bestContentCount) {
                                bestContentCount = highlightCount;
                                bestToggleContent = content;
                            }
                        });

                        // Decide which toggle to scroll to
                        if (bestToggleTitle && (bestTitleCount > 1 || bestContentCount <= 1)) {
                            scrollToToggle = bestToggleTitle;
                        } else {
                            scrollToToggle = bestToggleContent;
                        }

                        // Open all relevant toggles
                        toggleContents.forEach(content => {
                            if (searchWords.some(word => content.textContent.toLowerCase().includes(word.toLowerCase()))) {
                                content.style.display = 'block';
                            }
                        });
                    }
                }

                if (scrollToToggle) {
                    // Make it visible first
                    scrollToToggle.style.display = 'block';

                    // Rest of your debugging lines
                    console.log('Debug: scrollToToggle exists in DOM before scrolling.');
                    // ... (your other debug logs)

                    setTimeout(function () {
                        if (document.body.contains(scrollToToggle)) {
                            console.log('Debug: scrollToToggle still exists in DOM before scrolling.');
                            // Scroll to the selected toggle
                            scrollToToggle.scrollIntoView({ behavior: 'smooth' });
                            // Trigger a click event on the toggle
                            scrollToToggle.click();
                            // Debugging line to check if toggle is clicked
                            console.log('Debug: Clicking scrollToToggle');

                        } else {
                            console.log('Debug: scrollToToggle does NOT exist in DOM before scrolling.');
                        }
                    }, 1000); // Delay of 1000 milliseconds (1 second) before clicking the toggle
                } else {
                    // Debugging line if scrollToToggle is not found
                    console.log('Debug: scrollToToggle is null, no scroll and click.');
                }
            };
        </script>
        <?php
    }
}

