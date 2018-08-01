import json, argparse, ast, os

RUBRIC_FILE = os.path.dirname(os.path.abspath(__file__))+'/sttv_rubrics.py'

parser = argparse.ArgumentParser(description='Grades a submission')
parser.add_argument('submission', metavar='submission', type=str, nargs=1,
                    help='A submission object')

args = parser.parse_args()

userSubmission = args.submission[0]

# Shows the format of a submission.
example_submission = {
    'name': 'ACT English Practice 1',
    'timestamp' : 'whenever',
    'missed' : ['1', '4', '45', '55'],
    'blank' : ['55', '73', '74', '75'],
    'guessed' : ['23'],
}

# Grades a submission. This is designed to be imported by another file for use
# in another script.
def grade(submission):
    # Try to get the grading rubric from the RUBRIC_FILE
    try:
        with open(RUBRIC_FILE, 'r') as file:
            rubrics = json.loads(file.read())
            if submission['name'] in rubrics:
                rubric = rubrics[submission['name']]
            else:
                print('Could not find a rubric that matched this submission. \
                      Make sure a matching rubric has been parsed with \
                      csvparser.py and check that the value of RUBRIC_FILE \
                      matches the one in csvparser.py.')
                exit()
    except FileNotFoundError as e:
        print(e, '\nCheck that the RUBRIC_FILE (line 3) exists.')
        exit()

    # Use the submission as a basis for the format of the report
    report = submission

    report['playlist'] = {
            'questions' : [],
            'supplement' : []
    }

    # no need to re-compute this every time
    question_stem = rubric['data']['link'] + '/question-'

    # Adds a video to the 'question' playlist by question number
    def addQuestion(question_number):
        if question_stem != '/question-':
            report['playlist']['questions'].append(question_stem + str(question_number))

    # Adds a video to the 'supplement' playlist by link
    def addSupplement(link):
        report['playlist']['supplement'].extend(list(link))

    # Add appropriate question and supplement videos
    # for missed and guessed questions
    for question in submission['missed'] + submission['guessed']:
        addQuestion(question)
        addSupplement(rubric['grading'][question])

    # Determine number of blank questions
    left_blank = len(submission['blank'])

    # If more than the pre-set threshold were left blank,
    # add the appropriate video
    if left_blank >= int(rubric['analysis']['blank']['threshold']):
        addSupplement(rubric['analysis']['blank']['link'])

    # Get a raw score by subtracting the number of missed and blank
    # questions from the total number of questions in the
    raw_score = len(rubric['grading'].keys()) - len(list(set(submission['blank'] + submission['missed'])))
    report['raw_score'] = str(raw_score)

    # Calculate the converted score using the grading buckets
    buckets = rubric['buckets']
    while True:
        if raw_score < 0:
            print('Error: grading object malformatted or submission invalid')
            exit()
        if str(raw_score) in buckets:
            converted_score = str(buckets[str(raw_score)])
            break
        else:
            raw_score = raw_score - 1

    report['converted_score'] = converted_score

    # If the converted score is lower than the threshold,
    # add the appropriate video
    if int(converted_score) <= int(rubric['analysis']['score']['threshold']):
        addSupplement(rubric['analysis']['score']['link'])

    # Remove playlist duplicates
    for key in report['playlist']:
        report['playlist'][key] = list(set(report['playlist'][key]))

    print(report)

grade(userSubmission)